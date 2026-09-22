import { chromium, firefox } from '@playwright/test';
import assert from 'node:assert/strict';
const output = 'storage/app/refinement-after';
const base = process.env.CV_BASE_URL || 'http://127.0.0.1:8000';
const browser = await chromium.launch({headless:true});
const page = await browser.newPage({viewport:{width:1440,height:1000}});
const errors=[];page.on('pageerror', e=>errors.push(e.message));
for(const route of ['/cv','/cv/airbus-26']) {
 await page.goto(base+route);await page.locator('.cv-document').waitFor();await page.evaluate(()=>document.fonts.ready);
 const prefix=route==='/cv'?'base':'profile';
 console.log(prefix,'title',await page.title(),'milestones',await page.locator('[data-cv-milestone]').count());
 await page.screenshot({path:`${output}/${prefix}-closed.png`,fullPage:true});
 await page.locator('[data-cv-explore-open]').click();await page.waitForTimeout(300);
 const geometry=await page.locator('.pin-spacer').evaluate(el=>({top:el.getBoundingClientRect().top+scrollY,height:el.offsetHeight,stage:el.firstElementChild.offsetHeight}));
 const travel=geometry.height-geometry.stage;
 for(const [name,progress] of [['first',0],['middle',.5],['last',1],['reverse',.5]]) {
  await page.evaluate(y=>scrollTo(0,y),geometry.top-100+travel*progress);await page.waitForTimeout(200);
  await page.screenshot({path:`${output}/${prefix}-${name}.png`});
  assert.equal(await page.locator('[data-cv-milestone][data-active]').evaluate(el=>{const r=el.getBoundingClientRect(),s=el.closest('.cv-timeline__stage').getBoundingClientRect();return r.left>=s.left-1&&r.right<=s.right+1;}),true);
  console.log(prefix,name,await page.locator('[data-cv-milestone][data-active]').evaluate(el=>{const r=el.getBoundingClientRect(),s=el.closest('.cv-timeline__stage').getBoundingClientRect();return {title:el.querySelector('h3').textContent,inside:r.left>=s.left-1&&r.right<=s.right+1,bottom:r.bottom,stageBottom:s.bottom}}));
 }
 await page.keyboard.press('Escape');await page.waitForTimeout(300);
 console.log('closed spacers',await page.locator('.pin-spacer').count(),'focus',await page.locator('[data-cv-explore-open]').evaluate(el=>el===document.activeElement));
 await page.locator('[data-cv-explore-open]').click();await page.waitForTimeout(100);await page.keyboard.press('Escape');await page.waitForTimeout(300);
 await page.locator('[data-cv-explore-open]').click();
 await page.setViewportSize({width:1024,height:900});await page.waitForTimeout(250);
 assert.equal(await page.locator('.pin-spacer').count(),1);
 await page.emulateMedia({reducedMotion:'reduce'});await page.waitForTimeout(200);assert.equal(await page.locator('.pin-spacer').count(),0);
 await page.emulateMedia({reducedMotion:'no-preference'});await page.waitForTimeout(200);assert.equal(await page.locator('.pin-spacer').count(),1);
 await page.keyboard.press('Escape');await page.waitForTimeout(300);await page.setViewportSize({width:1440,height:1000});
}
for(const route of ['/cv/exp/volt-stade-kommunikation','/cv/edu/gymnasium-athenaeum-stade']) {await page.goto(base+route);console.log(route,await page.title());await page.screenshot({path:`${output}/${route.includes('/edu/')?'education':'experience'}.png`,fullPage:true});}
for(const [width,reduced] of [[390,false],[1440,true]]) {await page.setViewportSize({width,height:844});await page.emulateMedia({reducedMotion:reduced?'reduce':'no-preference'});await page.goto(base+'/cv');await page.locator('[data-cv-explore-open]').click();console.log(width,reduced,'overflow',await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),'spacers',await page.locator('.pin-spacer').count());await page.screenshot({path:`${output}/fallback-${width}.png`,fullPage:true});}
assert.deepEqual(errors,[]);console.log('errors',errors);await browser.close();
try {const ff=await firefox.launch();console.log('Firefox available');await ff.close();}catch{console.log('Firefox unavailable');}
