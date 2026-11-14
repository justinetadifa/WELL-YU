/* Reviewer controls use synthetic memory only. The real configuration validator
   is invoked as a pure PHP subprocess. No authenticated server session or SQL. */
const path=require('node:path'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright-core');
const base='http://localhost/WELL-YU';let browser;
(async()=>{
  browser=await chromium.launch({headless:true,executablePath:process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  const source=await browser.newContext(),response=await source.request.get(base+'/api/wellness.php'),metadata=await response.json();await source.close();
  for(const width of [1440,390]){
    const context=await browser.newContext({viewport:{width,height:1000},reducedMotion:'reduce'}),events=[];
    const evidence={source:'Synthetic source, not real records',observed_on:'2026-01-01',url:''};
    const service={id:101,owner_user_id:22,title:'Illustrative public service',category:'movement',barangay:metadata.barangays[0],review_status:'submitted',revision:1,payload:{operator_kind:'public',is_free:true,evidence}};
    const need={id:11,owner_user_id:22,title:'Illustrative access report',barangay:metadata.barangays[0],category:'movement',review_status:'reviewed',revision:1,payload:{intended_users:'Synthetic worker group',barriers:['travel'],notes:'Illustrative access issue',evidence}};
    const report={id:9,service_id:101,kind:'correction_report',created_at:'2026-01-01',payload:{notes:'Illustrative correction request',evidence}};
    const db={...metadata,workspace:{ready:true},signed_in:true,reviewer:true,services:[service],needs:[need],proposals:[],assessments:[],comparisons:[],configurations:[],pipeline:[],corrections:[report]};
    let imports=0,responses=0,reviews=0,unexpected=0;
    await context.route(base+'/wellness.php',async route=>{const response=await route.fetch();return route.fulfill({response,body:(await response.text()).replace('</head>','<script>window.SFC_APP_CONFIG.user={id:33,role:"admin",department:"LEBDO"};</script></head>')});});
    await context.route('**/api/**',async route=>{
      const request=route.request(),url=new URL(request.url()),action=url.searchParams.get('action')||'bootstrap';
      if(!url.pathname.endsWith('/wellness.php'))return route.fulfill({json:{}});
      const input=request.method()==='POST'?request.postDataJSON():{};let result={};
      if(action==='bootstrap')result=db;
      else if(action==='record')result={record:service};
      else if(action==='timeline')result={events:[...events,report],documents:[]};
      else if(action==='follow_up'){
        assert.equal(input.kind,'correction_response');assert.equal(Number(input.responds_to),9);assert.equal(input.record_kind,'service');assert.equal(input.record_id,101);
        responses++;db.corrections=[];events.unshift({id:10,kind:input.kind,created_at:'2026-01-01',payload:input});result={event:events[0]};
      }else if(action==='review'){
        assert.equal(input.kind,'service');assert.equal(input.id,101);
        assert(['under_review','reviewed'].includes(input.status));assert(input.scope&&input.notes);service.review_status=input.status;service.revision++;reviews++;result={record:service};
      }else if(action==='save_configuration'){
        let payload;try{payload=JSON.parse(execFileSync(process.env.PHP_BINARY||'php',[path.join(__dirname,'fixtures/wellness-engine.php')],{input:JSON.stringify({action:'configuration',input}),encoding:'utf8',stdio:['pipe','pipe','pipe']}));}catch{return route.fulfill({status:400,json:{error:'A validated configuration requires a documented validation reference.'}});}
        const configuration={id:db.configurations.length+1,name:payload.name,validation_status:payload.validation_status,payload};db.configurations.push(configuration);result={configuration};
      }else if(action==='import_preview')result={rows:input.records.map(record=>({record,duplicate:false})),can_commit:true,input_sha256:'illustrative-preview'};
      else if(action==='import_commit'){assert.equal(input.input_sha256,'illustrative-preview');imports++;result={ids:[102],review_status:'submitted'};}
      else{unexpected++;return route.fulfill({status:400,json:{error:'Unexpected synthetic action '+action}});}
      return route.fulfill({json:result});
    });
    await context.route('**/*',route=>{if(!['localhost','127.0.0.1'].includes(new URL(route.request().url()).hostname))return route.abort();return route.fallback();});
    const page=await context.newPage(),errors=[];page.on('pageerror',error=>errors.push(error.message));await page.goto(base+'/wellness.php',{waitUntil:'networkidle'});
    await page.locator('[data-tab=review]').click();await page.locator('[data-correction-response="9"]').click();
    let form=page.locator('[data-form=follow]');assert.equal(await form.locator('[name=responds_to]').inputValue(),'9');
    for(const [name,value]of Object.entries({responsible:'Synthetic reviewer',notes:'Evidence checked within illustrative scope',next_action:'Confirm current schedule',source:evidence.source,observed_on:evidence.observed_on}))await form.locator(`[name=${name}]`).fill(value);
    await form.locator('[type=submit]').click();await page.waitForFunction(()=>document.querySelector('#wellnessNotice').textContent.startsWith('Saved.'));assert.equal(responses,1);
    await page.locator('[data-tab=review]').click();assert.equal(await page.locator('[data-correction-response]').count(),0);
    for(const status of ['under_review','reviewed']){
      form=page.locator('[data-form=review]');await form.locator('[name=parent]').selectOption('service:101');await form.locator('[name=status]').selectOption(status);await form.locator('[name=scope]').fill('Illustrative hours and source only');await form.locator('[name=notes]').fill('No official clearance asserted');await form.locator('[type=submit]').click();await page.waitForFunction(()=>document.querySelector('#wellnessNotice').textContent.startsWith('Saved.'));
    }
    assert.equal(reviews,2);await page.locator('[data-tab=settings]').click();form=page.locator('[data-form=configuration]');await form.locator('[name=name]').fill('Illustrative configuration');await form.locator('[name=validation_status]').selectOption('validated');
    for(const [i,key]of Object.keys(metadata.criteria).entries()){await form.locator(`[name=weight_${key}]`).fill(String([20,15,20,15,15,15][i]));await form.locator(`[name=direction_${key}]`).selectOption('maximize');}
    await form.locator('[type=submit]').click();await page.waitForFunction(()=>document.querySelector('#wellnessNotice').classList.contains('is-error'));assert.equal(db.configurations.length,0);
    await form.locator('[name=validation_status]').selectOption('provisional');await form.locator('[type=submit]').click();await page.waitForFunction(()=>document.querySelector('#wellnessNotice').textContent.startsWith('Saved.'));assert.equal(db.configurations[0].validation_status,'provisional');
    form=page.locator('[data-form=import]');await form.locator('[name=kind]').selectOption('service');const records=JSON.stringify([{title:'Illustrative import',category:'movement',barangay:metadata.barangays[0],operator_kind:'public',evidence}]);await form.locator('[name=records]').fill(records);await form.locator('[type=submit]').click();await page.waitForFunction(()=>document.querySelector('#importPreview').textContent.includes('1 validated rows'));
    await form.locator('[name=records]').fill(records+' ');await page.locator('[data-action=commit-import]').click();await page.waitForFunction(()=>document.querySelector('#wellnessNotice').textContent.includes('Validate the current import'));assert.equal(imports,0);
    await form.locator('[type=submit]').click();await page.locator('[data-action=commit-import]').click();await page.waitForFunction(()=>document.querySelector('#wellnessNotice').textContent.includes('Import committed'));assert.equal(imports,1);
    await page.locator('[data-tab=needs]').click();await page.locator('[data-need-proposal="11"]').click();assert.equal(await page.locator('#proposalForm [name=source_need_id]').inputValue(),'11');assert.equal(await page.locator('#proposalForm [name=intended_users]').inputValue(),'Synthetic worker group');
    assert.equal(errors.length,0,errors.join('\n'));assert.equal(unexpected,0);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
    console.log(`PASS ${width}px: correction response, scoped review states, provisional configuration, import preview invalidation/commit and need-to-proposal prefill; synthetic memory only.`);await context.close();
  }
})().catch(error=>{console.error(error.stack);process.exitCode=1;}).finally(async()=>{if(browser)await browser.close();});
