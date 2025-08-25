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
  const check = await request.get(`/api/test_user_lookup.php?username=${uname}`);
  const data = await check.json();
  expect(data.ok).toBeTruthy();
});

test('redirects to jobs on successful login', async ({ page, context }) => {
  await page.goto('/login.php');
  await expect(page.locator('input[name="csrf_token"][type="hidden"]')).toHaveCount(1);
  const cookies = await context.cookies();
  const sessionCookie = cookies.find(c => c.name === 'PHPSESSID');
  expect(sessionCookie).toBeTruthy();

  await page.fill('#username', creds.username);
  await page.fill('#password', creds.password);
  const jsonPromise = page
    .waitForResponse('**/api/login.php')
    .then(res => res.json());
  await page.click('button[type="submit"]');
  const json = await jsonPromise;
  expect(json).toEqual(expect.objectContaining({ ok: true, role: 'user' }));
  await expect(page).toHaveURL('/jobs.php');
});

test('shows error on invalid credentials', async ({ page }) => {
  await page.goto('/login.php');
  await page.fill('#username', creds.username);
  await page.fill('#password', 'wrongpass');
  await page.click('button[type="submit"]');
  await expect(page.locator('#login-error')).toHaveText(/Invalid credentials/i);
});
