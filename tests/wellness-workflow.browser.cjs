/* Browser workflow uses a synthetic in-memory API. No accounts or SQL fixtures
   are created. PHP calculation/PDF code is called as a pure CLI subprocess. */
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright-core');
const root=path.resolve(__dirname,'..'),out=path.join(root,'.ui-audit'),base='http://localhost/WELL-YU';
const run=input=>execFileSync(process.env.PHP_BINARY||'php',[path.join(__dirname,'fixtures/wellness-engine.php')],{input:JSON.stringify(input),encoding:null});
const calculate=input=>JSON.parse(run(input).toString());
let browser;
(async()=>{
  fs.mkdirSync(out,{recursive:true});
  browser=await chromium.launch({headless:true,executablePath:process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  // Fetch only the live read-only metadata; extension tables intentionally remain uninitialized.
  const source=await browser.newContext();
  const live=await source.request.get(base+'/api/wellness.php');assert.equal(live.status(),200);
  const metadata=await live.json();await source.close();
  for(const width of [1440,390]){
    const context=await browser.newContext({viewport:{width,height:1000},reducedMotion:'reduce'});
    const weights=Object.fromEntries(Object.keys(metadata.criteria).map((key,i)=>[key,[20,15,20,15,15,15][i]]));
    const configurationInput={name:'Illustrative browser fixture',weights};
    const rules=Object.fromEntries(Object.keys(weights).map(key=>[key,{weight:weights[key],min:0,max:100,direction:'maximize',unit:'documented rating'}]));
    const configuration={id:1,name:configurationInput.name,validation_status:'provisional',validation_reference:'',payload:{name:configurationInput.name,criteria:rules,validation_status:'provisional'}};
    const db={...metadata,workspace:{ready:true},signed_in:true,reviewer:false,services:[],needs:[],proposals:[],configurations:[configuration],assessments:[],comparisons:[],pipeline:[],corrections:[]};
    const scenarios=[];let realWriteAttempts=0;
    await context.route(base+'/wellness.php',async route=>{
      const response=await route.fetch();const body=(await response.text()).replace('</head>','<script>window.SFC_APP_CONFIG.user={id:11,role:"investor",name:"Synthetic browser actor"};</script></head>');
      return route.fulfill({response,body});
    });
    await context.route('**/api/**',async route=>{
      const request=route.request(),url=new URL(request.url()),action=url.searchParams.get('action')||'bootstrap';
      if(!url.pathname.endsWith('/wellness.php'))return route.fulfill({json:{properties:[],meta:{},stats:{}}});
      let input={};if(request.method()==='POST')input=request.postDataJSON();
      let result={};
      if(action==='bootstrap')result=db;
      else if(action==='save_service'){const row={id:db.services.length+1,owner_user_id:11,title:input.title,category:input.category,barangay:input.barangay,lat:null,lng:null,payload:input,review_status:'submitted',revision:1};db.services.push(row);result={record:row};}
      else if(action==='save_need'){const row={id:db.needs.length+1,owner_user_id:11,title:input.title,category:input.category,barangay:input.barangay,payload:input,review_status:'submitted',revision:1};db.needs.push(row);result={record:row};}
      else if(action==='save_proposal'){const row={id:db.proposals.length+1,owner_user_id:11,title:input.title,mode:input.mode,property_id:null,service_id:null,review_status:'submitted',revision:1,payload:input};db.proposals.push(row);result={proposal:row};}
      else if(action==='preview_assessment')result={result:calculate({action:'assessment',input,configuration:input.configuration})};
      else if(action==='save_assessment'){
        const proposal=db.proposals.find(row=>row.id===input.proposal_id),record={id:db.assessments.length+1,proposal_id:proposal.id,configuration_id:1,snapshot:{proposal,configuration,property_id:null,observations:input},result:calculate({action:'assessment',input,configuration:configurationInput})};
        db.assessments.unshift(record);result={assessment:record};
      }
      else if(action==='preview_scenario')result={result:calculate({action:'scenario',input,baseline:input.baseline})};
      else if(action==='save_scenario'){
        const baseline=scenarios.find(row=>row.id===Number(input.baseline_id));const row={id:scenarios.length+1,proposal_id:input.proposal_id,name:input.name,input,baseline_id:baseline?.id||null,is_baseline:baseline?0:1,result:calculate({action:'scenario',input,baseline:baseline?.result})};scenarios.push(row);result={scenario:row};
      }
      else if(action==='scenarios')result={scenarios:scenarios.filter(row=>row.proposal_id===Number(url.searchParams.get('id')))};
      else if(action==='save_comparison'){
        const snapshot={assessments:input.assessment_ids.map(id=>db.assessments.find(row=>row.id===id)),scenarios:{}};
        snapshot.assessments.forEach(a=>snapshot.scenarios[a.proposal_id]=scenarios.filter(row=>row.proposal_id===a.proposal_id));
        const row={id:db.comparisons.length+1,name:input.name,created_at:'2026-01-01',snapshot_sha256:'synthetic-browser-snapshot',snapshot};db.comparisons.push(row);result={comparison:row};
      }
      else if(action==='comparison')result={comparison:db.comparisons.find(row=>row.id===Number(url.searchParams.get('id')))};
      else if(action==='export'){
        const comparison=db.comparisons.find(row=>row.id===Number(url.searchParams.get('id')));
        return route.fulfill({status:200,headers:{'content-type':'application/pdf','content-disposition':'attachment; filename="welyu-fixture-brief.pdf"'},body:run({action:'pdf',comparison})});
      }
      else {realWriteAttempts++;return route.fulfill({status:400,json:{error:'Unexpected synthetic test action: '+action}});}
      return route.fulfill({status:200,json:result});
    });
    await context.route('**/*',route=>{const url=new URL(route.request().url());if(!['localhost','127.0.0.1'].includes(url.hostname))return route.abort();return route.fallback();});
    const page=await context.newPage(),errors=[];page.on('pageerror',error=>errors.push(error.message));
    await page.goto(base+'/wellness.php',{waitUntil:'networkidle'});
    const fill=async(form,name,value)=>form.locator(`[name="${name}"]`).fill(String(value));
    const choose=async(form,name,value)=>form.locator(`[name="${name}"]`).selectOption(String(value));
    const category='movement',barangay=metadata.barangays[0];
    const sourceEvidence=async form=>{await fill(form,'source','Synthetic browser source, not real data');await fill(form,'observed_on','2026-01-01');};
    let form=page.locator('[data-form=service]');await fill(form,'title','Illustrative movement service');await choose(form,'category',category);await choose(form,'barangay',barangay);await choose(form,'operator_kind','public');await sourceEvidence(form);await form.locator('[type=submit]').click();await page.waitForFunction(()=>document.querySelector('#serviceResults').textContent.includes('Illustrative movement service'));
    await page.locator('[data-tab=needs]').first().click();form=page.locator('[data-form=need]');await fill(form,'title','Illustrative access need');await choose(form,'category',category);await choose(form,'barangay',barangay);await fill(form,'intended_users','Synthetic worker group');await fill(form,'notes','Illustrative documented travel barrier');await form.locator('[name=barrier_travel]').check();await sourceEvidence(form);await form.locator('[type=submit]').click();await page.waitForFunction(()=>document.querySelector('#wellnessView').textContent.includes('Illustrative access need'));
    await page.locator('[data-need-proposal]').first().click();assert.equal(await page.locator('#proposalForm [name=source_need_id]').inputValue(),'1');
    for(const [title,value]of [['Illustrative site A',50],['Illustrative site B',70]]){
      form=page.locator('[data-form=proposal]');await fill(form,'title',title);await choose(form,'mode','open');await choose(form,'category',category);await fill(form,'intended_users','Synthetic worker group');await fill(form,'concept','Illustrative movement service concept');await sourceEvidence(form);await form.locator('[type=submit]').click();
      await page.waitForFunction(t=>[...document.querySelector('[name=assessment_proposal]').selectedOptions].some(o=>o.textContent===t),title);
      form=page.locator('[data-form=assessment]');await choose(form,'configuration_id','1');
      for(const key of Object.keys(weights)){await fill(form,'value_'+key,value);await fill(form,'source_'+key,'Synthetic observation');await fill(form,'date_'+key,'2026-01-01');}
      await form.locator('[type=submit]').click();await page.waitForFunction(()=>document.querySelector('#assessmentResult').textContent.includes('Evidence coverage'));
      await page.locator('[data-action=save-assessment]').click();await page.waitForFunction(()=>document.querySelector('#wellnessNotice').textContent.includes('Assessment version saved'));
    }
    await choose(page.locator('#wellnessView'),'proposal_choice','1');await page.locator('[data-action=open-simulator]').click();form=page.locator('[data-form=scenario]');
    await fill(form,'name','Illustrative baseline');await fill(form,'price_per_visit_php',100);await fill(form,'variable_cost_php',40);await fill(form,'fixed_monthly_cost_php',12000);await fill(form,'expected_monthly_customers',300);await fill(form,'assumptions','Synthetic assumptions only');await fill(form,'quote_source','Synthetic quotation');await fill(form,'quote_observed_on','2026-01-01');
    await page.locator('[data-action=save-scenario]').click();await page.waitForFunction(()=>document.querySelector('#scenarioList').textContent.includes('Illustrative baseline'));
    await choose(form,'baseline_id','1');await fill(form,'name','Illustrative longer-hours alternative');await fill(form,'price_per_visit_php',120);await page.locator('[data-action=save-scenario]').click();await page.waitForFunction(()=>document.querySelector('#scenarioList').textContent.includes('longer-hours'));
    assert.equal(scenarios[0].result.inputs.price_per_visit_php,100);assert.equal(scenarios[1].result.break_even_visits_per_month,150);
    await page.locator('[data-scenario-draft="2"]').click();assert.equal(await form.locator('[name=price_per_visit_php]').inputValue(),'120');assert.equal(await form.locator('[name=baseline_id]').inputValue(),'1');assert.equal(scenarios.length,2);
    await page.locator('[data-tab=compare]').first().click();form=page.locator('[data-form=comparison]');await fill(form,'name','Illustrative A / B comparison');await form.locator('[type=checkbox]').nth(0).check();await form.locator('[type=checkbox]').nth(1).check();await form.locator('[type=submit]').click();await page.waitForFunction(()=>document.querySelector('#comparisonList').textContent.includes('Illustrative A / B comparison'));
    await page.locator('[data-comparison]').click();await page.waitForFunction(()=>document.querySelector('#comparisonResult').textContent.includes('Saved snapshot fingerprint'));
    assert.equal(db.comparisons[0].snapshot.assessments.length,2);assert.equal(db.comparisons[0].snapshot.scenarios[1].length,2);
    const downloaded=page.waitForEvent('download');await page.getByRole('link',{name:/Download PDF brief/}).first().click();const download=await downloaded;const file=path.join(out,`wellness-fixture-${width}.pdf`);await download.saveAs(file);assert(fs.readFileSync(file).subarray(0,8).toString()==='%PDF-1.4');
    assert.equal(errors.length,0,errors.join('\n'));assert.equal(realWriteAttempts,0);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
    await page.screenshot({path:path.join(out,`wellness-comparison-${width}.png`),fullPage:true,animations:'disabled'});
    console.log(`PASS ${width}px: service, need, two proposals/assessments, baseline/alternative, saved comparison and actual PDF bytes; synthetic memory only.`);
    await context.close();
  }
})().catch(error=>{console.error(error.stack);process.exitCode=1;}).finally(async()=>{if(browser)await browser.close();});
