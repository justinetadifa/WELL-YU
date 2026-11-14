/* Public interface QA only. No authentication, forms, API calls or database writes.
   PLAYWRIGHT_MODULE may point to an installed playwright-core module. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const root = path.resolve(__dirname, '..');
const artifacts = path.join(root, '.ui-audit');
const base = 'http://localhost/WELL-YU';
let browser;

(async () => {
  fs.mkdirSync(artifacts, {recursive:true});
  browser = await chromium.launch({headless:true, executablePath:process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  const reports=[];
  for (const width of [1440,768,390]) {
    const context=await browser.newContext({viewport:{width,height:width===390?844:1000},reducedMotion:'reduce'});
    let blockedApiRequests=0;
    // GET APIs in the existing application can record metrics. Keep visual QA
    // independent of them by rendering an explicitly empty browser-only response.
    await context.route('**/api/**',route=>{
      blockedApiRequests++;
      return route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({properties:[],users:[],voteOptions:[],showcaseItems:[],stats:{},meta:{},services:{}})});
    });
    await context.route('**/*',route=>{
      const request=route.request(),url=new URL(request.url());
      if(!['GET','HEAD'].includes(request.method()) || !['localhost','127.0.0.1'].includes(url.hostname))return route.abort();
      return route.fallback();
    });
    for(const [name,suffix] of [['home','/'],['login','/investor-login.php'],['signup','/investor-login.php?mode=signup'],['broker','/seller-login.php'],['staff','/admin-login.php'],['pipeline','/city-pipeline.php'],['privacy','/privacy.php']]){
      const page=await context.newPage(),errors=[];
      page.on('pageerror',error=>errors.push(error.message));
      const response=await page.goto(base+suffix,{waitUntil:'networkidle'});
      await page.evaluate(()=>document.fonts.ready);
      if(await page.locator('#cityPrivacyDialog[open]').count()){
        await page.screenshot({animations:'disabled',path:path.join(artifacts,`welyu-privacy-dialog-${width}.png`)});
        await page.locator('#cityPrivacyGuest').click();
        await page.waitForFunction(()=>!document.querySelector('#cityPrivacyDialog')?.open);
      }
      const info=await page.evaluate(()=>({
        width:innerWidth,scrollWidth:document.documentElement.scrollWidth,title:document.title,
        brand:document.querySelector('.city-brand-text strong')?.textContent,
        background:getComputedStyle(document.body).backgroundColor,
        h1:document.querySelector('h1')?.textContent,
        actionColor:getComputedStyle(document.querySelector('.city-cta-button')).backgroundColor,
      }));
      assert.equal(response.status(),200,`${name}: HTTP response`);
      assert(info.scrollWidth<=width+1,`${name}: overflow at ${width}`);
      assert.equal(info.brand,'wELYU.');
      assert.equal(info.actionColor,'rgb(229, 251, 185)');
      assert.equal(errors.length,0,`${name}: ${errors.join(', ')}`);
      if(name==='signup')assert.equal(info.h1,'Create your account','Signup heading must survive client initialization.');
      await page.screenshot({animations:'disabled',path:path.join(artifacts,`welyu-${name}-${width}.png`),fullPage:name!=='home'});
      if(name==='home'){
        assert.equal(await page.locator('#cityPrivacyDialog').evaluate(node=>getComputedStyle(node).display),'none');
        const action=page.getByRole('link',{name:'Explore possibilities'});
        await action.focus();
        await page.keyboard.press('Tab');await page.keyboard.press('Shift+Tab');
        assert.equal(await action.evaluate(node=>getComputedStyle(node).outlineStyle),'solid');
        await action.click();await page.waitForFunction(()=>location.hash==='#properties');
        await page.locator('#about').scrollIntoViewIfNeeded();
        await page.screenshot({animations:'disabled',path:path.join(artifacts,`welyu-about-${width}.png`)});
      }
      if(name==='login'){
        const toggle=page.getByRole('button',{name:/show password/i});
        if(await toggle.count()){
          await toggle.first().click();
          assert.equal(await page.locator('input[name=password]').getAttribute('type'),'text');
          await page.getByRole('button',{name:/hide password/i}).first().click();
          assert.equal(await page.locator('input[name=password]').getAttribute('type'),'password');
        }
      }
      if(name==='pipeline'){
        await page.locator('[data-locus-help-trigger]').first().click();
        await page.waitForFunction(()=>document.querySelector('#locusHelpDialog')?.open);
        await page.screenshot({animations:'disabled',path:path.join(artifacts,`welyu-help-${width}.png`)});
        await page.keyboard.press('Escape');
        await page.waitForFunction(()=>!document.querySelector('#locusHelpDialog')?.open);
      }
      if(width<=768){
        await page.locator('#cityMenuButton').click();
        assert.equal(await page.locator('#cityMenuButton').getAttribute('aria-expanded'),'true');
        await page.locator('#cityMenuButton').click();
      }
      reports.push({name,...info,errors});await page.close();
    }
    console.log(`PASS ${width}px: 7 public routes, navigation, form controls, dialogs, anchors, focus and overflow; ${blockedApiRequests} API requests intercepted.`);
    await context.close();
  }
  fs.writeFileSync(path.join(artifacts,'welyu-preview-results.json'),JSON.stringify(reports,null,2));
  console.log('No accounts created or authenticated workflows submitted.');
})().catch(error=>{console.error(error.message);process.exitCode=1;}).finally(async()=>{if(browser)await browser.close();});
