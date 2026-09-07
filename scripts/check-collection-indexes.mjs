import { chromium, expect } from '@playwright/test';
// Run against the generated site served on port 8017, or set COLLECTION_BASE_URL.
const base = process.env.COLLECTION_BASE_URL || 'http://127.0.0.1:8017';
const b=await chromium.launch();const errors=[];
for(const width of [1920,1280,768,390]) for(const theme of ['light','dark']) {
 const c=await b.newContext({viewport:{width,height:900},colorScheme:theme});const p=await c.newPage();p.on('pageerror',e=>errors.push(e.message));p.on('console',m=>{if(m.type()==='error')errors.push(m.text())});
 for(const route of ['projekte','blog','publikationen']) {
  await p.goto(base+'/'+route+'/');await p.waitForLoadState('networkidle');
  if(route==='projekte')await expect(p.locator('[data-project-stream]')).toHaveClass(/is-enhanced/);
  expect(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
  expect(await p.locator('main').innerText()).not.toContain('Demo');
  for (const img of await p.locator('main img:visible').all()) {
   await img.scrollIntoViewIfNeeded();
   await expect.poll(() => img.evaluate(el => el.complete && el.naturalWidth > 0)).toBe(true);
  }
  await p.evaluate(() => scrollTo(0, 0));
  await p.waitForTimeout(200);
  await p.screenshot({path:`/tmp/collection-${route}-${width}-${theme}.png`,fullPage:true});
  if(route==='projekte' && width>=1024){
   const rows=p.locator('[data-project-record]');await rows.nth(1).hover();await expect(p.locator('[data-project-preview]')).toContainText('ai-ctx');
   await rows.nth(2).locator('a').focus();await expect(p.locator('[data-project-preview]')).toContainText('AtheBlues');
   await p.screenshot({path:`/tmp/collection-focus-${width}-${theme}.png`});
   await p.locator('a[href="#main"]').last().focus();
   await p.evaluate(() => document.activeElement.blur());
   await p.mouse.move(width - 1, 1);
   await p.evaluate(() => scrollTo(0, 0));
   await p.waitForTimeout(100);
   await rows.nth(2).evaluate(el => scrollTo(0, el.getBoundingClientRect().top + scrollY - 200));
   await expect(p.locator('[data-project-preview]')).toContainText('AtheBlues');
  }
  if(route==='publikationen'){await p.locator('summary').first().focus();await p.keyboard.press('Enter');await expect(p.locator('details').first()).toHaveAttribute('open','');}
 }
 await c.close();
}
for(const js of [true,false]) {
 const c=await b.newContext({javaScriptEnabled:js,reducedMotion:'reduce',viewport:{width:1280,height:900}});const p=await c.newPage();
 for(const route of ['projekte','blog','publikationen']){await p.goto(base+'/'+route+'/');await p.waitForLoadState('networkidle');expect(await p.locator('main a').count()).toBeGreaterThan(0);if(route==='projekte' && js){await p.locator('[data-project-link]').nth(1).focus();expect(await p.locator('[data-project-preview]').evaluate(e=>e.getAnimations({subtree:true}).length)).toBe(0);}}
 await c.close();
}
console.log(JSON.stringify({matrix:24,errors}));expect(errors).toEqual([]);await b.close();
