const { test, expect } = require('@playwright/test');

const creds = { username: '', password: 'Passw0rd1' };

test.beforeAll(async ({ request }) => {
  const uname = `testuser_${Date.now()}`;
  creds.username = uname;
  await request.post('/api/register.php', {
    form: {
      username: uname,
      email: `${uname}@example.com`,
      password: creds.password,
    },
  });
});

test('redirects to jobs on successful login', async ({ page }) => {
  await page.goto('/login.php');
  await page.fill('#username', creds.username);
  await page.fill('#password', creds.password);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/jobs.php');
});

test('shows error on invalid credentials', async ({ page }) => {
  await page.goto('/login.php');
  await page.fill('#username', creds.username);
  await page.fill('#password', 'wrongpass');
  await page.click('button[type="submit"]');
  await expect(page.locator('#login-error')).toHaveText(/Invalid credentials/i);
});
