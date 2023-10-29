import puppeteer from 'puppeteer';

(async () => {
    const browser = await puppeteer.launch({
        executablePath: '/usr/bin/chromium-browser',  // adjust this to the path of your Chrome or Chromium binary
        headless: true,
        args: ['--no-sandbox']
    });

    const page = await browser.newPage();
    await page.goto('https://www.google.com');
    await page.screenshot({path: 'google.png'});
    await browser.close();
})();
