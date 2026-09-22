import { chromium, firefox } from '@playwright/test';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { readFile, writeFile, unlink } from 'node:fs/promises';
import assert from 'node:assert/strict';
const exec = promisify(execFile);
const boot = 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();';
const base = process.env.CV_BASE_URL || 'http://127.0.0.1:8000';
const path = 'content/collections/cv_profiles/cv-qa-hidden.md';
const id = crypto.randomUUID();
const tokens = [];
const globalsPath = 'content/globals/jacks-portfolio/cv.yaml';
const originalGlobals = await readFile(globalsPath, 'utf8');
const issue = async scope => {
 const {stdout} = await exec('php',['-r',boot+`echo json_encode(App\\Support\\CvCapabilities::issueTemporary('${scope}'));`]);
 const capability = JSON.parse(stdout);tokens.push(capability);return capability.token;
};
try {
 const {stdout: encrypted} = await exec('php',['-r',boot+`$data=[];foreach(['private_email'=>'cv-session-test@example.invalid','phone'=>'+49 000 000000','street'=>'Teststraße','house_number'=>'1','postal_code'=>'21600','city'=>'Teststadt','country'=>'Testland','date_of_birth'=>'2000-01-01','place_of_birth'=>'Teststadt'] as $key=>$value) $data[$key]=Illuminate\\Support\\Facades\\Crypt::encryptString($value);echo json_encode($data);`]);
 let fixtureGlobals=originalGlobals;
 for(const [key,value] of Object.entries(JSON.parse(encrypted))) fixtureGlobals=fixtureGlobals.replace(new RegExp('^'+key+':.*$','m'),key+': '+JSON.stringify(value));
 await writeFile(globalsPath,fixtureGlobals);
 let profile = await readFile('content/collections/cv_profiles/jobmesse-26.md','utf8');
 profile=profile.replace(/^id: .*$/m,`id: ${id}`).replace(/^title: .*$/m,"title: 'CV QA hidden'").replace(/^---\n/, '---\ninteractive_timeline: hide\n');
 await writeFile(path,profile);await exec('php',['artisan','statamic:stache:clear']);
 for(const [name,engine] of [['chromium',chromium],['firefox',firefox]]) {
  let browser;
  try {browser=await engine.launch(name === 'chromium' ? { channel: 'chromium' } : {});}catch(error){console.log(name,'unavailable:',error.message.split('\n')[0]);continue;}
  try {
   const context=await browser.newContext({viewport:{width:1440,height:1000}});const page=await context.newPage();
   const requests=[];context.on('request',req=>requests.push(req.url()));
   await page.goto(base+'/cv/cv-qa-hidden');await page.locator('.cv-document').waitFor();await page.waitForFunction(()=>performance.getEntriesByType('resource').some(x=>x.name.includes('cv-print-')));
   assert.equal(await page.locator('[data-cv-explore]').count(),0);
   assert.equal(requests.some(url=>url.includes('cv-explore-')),false);
   await page.screenshot({path:`storage/app/refinement-after/${name}-hidden.png`,fullPage:true});
   const token=await issue('/cv/jobmesse-26');
   const response=await page.goto(base+'/cv/jobmesse-26#cv='+token);
   await page.waitForURL(base+'/cv/jobmesse-26');
   await page.waitForFunction(()=>document.documentElement.dataset.cvPrivateState==='authorized');
   assert.equal(await page.locator('.cv-contact__mask').count(),0);
   assert.ok(response.headers()['cache-control'].includes('no-store'));
   assert.equal((await page.content()).includes(token),false);
   const cookies=await context.cookies();assert.ok(cookies.some(cookie=>cookie.httpOnly&&cookie.sameSite==='Lax'));
   // Check the real PDF response through the same browser session. Never persist actual private PDF bytes.
   const pdf=await context.request.get(base+'/cv/jobmesse-26/pdf');assert.equal(pdf.status(),200);assert.equal((await pdf.body()).subarray(0,5).toString(),'%PDF-');
   await page.evaluate(()=>{window.__htmlPrintCalls=0;window.print=()=>window.__htmlPrintCalls++;});
   const popupPromise=page.waitForEvent('popup');
   const pdfRequest=context.waitForEvent('request', { predicate: req => req.url() === base+'/cv/jobmesse-26/pdf' });
   await page.keyboard.press('Control+p');const popup=await popupPromise;
   await pdfRequest;
   assert.equal(await page.evaluate(()=>window.__htmlPrintCalls),0);
   console.log(name,'Ctrl+P opened canonical profile PDF; HTML print calls: 0');
   if (!popup.isClosed()) await popup.close();
   const cmdPopupPromise=page.waitForEvent('popup');
   const cmdPdfRequest=context.waitForEvent('request', { predicate: req => req.url() === base+'/cv/jobmesse-26/pdf' });
   await page.keyboard.press('Meta+p');const cmdPopup=await cmdPopupPromise;
   await cmdPdfRequest;
   if (!cmdPopup.isClosed()) await cmdPopup.close();
   await page.goto(base+'/cv');await page.waitForFunction(()=>document.documentElement.dataset.cvPrivateState==='public');
   assert.equal(await page.locator('.cv-contact__mask').count(),3);
   console.log(name,'hidden timeline, scoped private HTML/PDF session and public masking passed');
   await context.close();
  }finally{await browser.close();}
 }
 // Application log check prints only the verdict, never tokens or log contents.
 const log=await readFile('storage/logs/laravel.log','utf8').catch(()=> '');
 assert.equal(tokens.some(({token})=>log.includes(token)),false);
 console.log('Application logs contain none of the issued test tokens.');
}finally{
 await writeFile(globalsPath,originalGlobals);
 await unlink(path).catch(()=>{});
 for(const {identifier} of tokens) await exec('php',['-r',boot+`Illuminate\\Support\\Facades\\DB::table('cv_access_tokens')->where('identifier','${identifier}')->delete();`]);
 await exec('php',['artisan','statamic:stache:clear']);
}
