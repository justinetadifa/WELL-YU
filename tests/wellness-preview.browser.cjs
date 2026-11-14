/* Live guest QA: only bootstrap SELECTs and stateless preview calculations may
   reach the API. Every other API call and every external request is blocked. */
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright-core');
const base='http://localhost/WELL-YU',out=path.resolve(__dirname,'../.ui-audit');
let browser;
(async()=>{
  fs.mkdirSync(out,{recursive:true});
  browser=await chromium.launch({headless:true,executablePath:process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  for(const width of [1440,390]){
    const context=await browser.newContext({viewport:{width,height:1000},reducedMotion:'reduce'});
    let unexpected=0;
    await context.route('**/*',route=>{
      const request=route.request(),url=new URL(request.url());
      if(!['localhost','127.0.0.1'].includes(url.hostname))return route.abort();
      if(url.pathname.includes('/api/')){
        const action=url.searchParams.get('action')||'bootstrap';
        if(!url.pathname.endsWith('/wellness.php')||!((request.method()==='GET'&&action==='bootstrap')||(request.method()==='POST'&&['preview_assessment','preview_scenario'].includes(action)))){unexpected++;return route.abort();}
      }else if(request.method()!=='GET'){unexpected++;return route.abort();}
      return route.continue();
    });
    const page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
    await page.goto(base+'/wellness.php',{waitUntil:'networkidle'});
    await page.waitForFunction(()=>document.querySelector('[data-form=service]'));
    const csrf=await page.evaluate(()=>window.SFC_APP_CONFIG.csrfToken);
    const rejected=await context.request.post(base+'/api/wellness.php?action=preview_scenario',{data:{},headers:{'X-CSRF-Token':'invalid'}});
    assert.equal(rejected.status(),419,'Stateless previews still require CSRF protection.');
    const malformed=await context.request.post(base+'/api/wellness.php?action=preview_scenario',{data:{quote_evidence:'not an object'},headers:{'X-CSRF-Token':csrf}});
    assert.equal(malformed.status(),400,'Malformed evidence is a validation error.');
    const privateExport=await context.request.get(base+'/api/wellness.php?action=export&id=1');
    assert([401,503].includes(privateExport.status()),'Guest cannot download a private brief.');
    assert(await page.locator('[data-form=service] [type=submit]').isDisabled());
    await page.screenshot({path:path.join(out,`wellness-directory-${width}.png`),fullPage:true,animations:'disabled'});
    await page.locator('[data-tab=plan]').click();
    const form=page.locator('[data-form=assessment]');
    const keys=await form.locator('[name^=weight_]').evaluateAll(nodes=>nodes.map(n=>n.name.slice(7)));
    for(const [i,key]of keys.entries()){
      await form.locator(`[name=weight_${key}]`).fill(String([20,15,20,15,15,15][i]));
      await form.locator(`[name=value_${key}]`).fill('50');
      await form.locator(`[name=source_${key}]`).fill('Illustrative browser preview; never saved');
      await form.locator(`[name=date_${key}]`).fill('2026-01-01');
    }
    await form.locator('[type=submit]').click();
    await page.waitForFunction(()=>document.querySelector('#assessmentResult .wy-metric strong')?.textContent==='50');
    const scores=await page.locator('#assessmentResult .wy-metric strong').allTextContents();
    assert.equal(scores[0],'50');assert.equal(scores[1],'\u2014');assert.equal(scores[2],'100%');
    assert(await page.locator('[data-action=save-assessment]').isDisabled());
    await page.locator('[data-action=open-simulator]').click();
    const scenario=page.locator('[data-form=scenario]');
    for(const [name,value]of Object.entries({price_per_visit_php:120,variable_cost_php:40,fixed_monthly_cost_php:12000,quote_source:'Illustrative quotation, not real data',quote_observed_on:'2026-01-01',assumptions:'Stateless arithmetic preview only',name:'Unsaved preview'}))await scenario.locator(`[name=${name}]`).fill(String(value));
    await scenario.locator('[type=submit]').click();
    await page.waitForFunction(()=>document.querySelector('#scenarioResult .wy-metric strong')?.textContent==='150');
    assert(await page.locator('[data-action=save-scenario]').isDisabled());
    assert.equal(await page.locator('#scenarioResult .wy-metric strong').nth(1).textContent(),'\u2014');
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
    await page.screenshot({path:path.join(out,`wellness-preview-${width}.png`),fullPage:true,animations:'disabled'});
    assert.equal(errors.length,0,errors.join('\n'));assert.equal(unexpected,0);
    console.log(`PASS ${width}px: real guest page, MCE 50 / IAI withheld, break-even 150, unsaved inputs and disabled persistence; SELECTs and stateless calculations only.`);
    await context.close();
  }
})().catch(error=>{console.error(error.stack);process.exitCode=1;}).finally(async()=>{if(browser)await browser.close();});
