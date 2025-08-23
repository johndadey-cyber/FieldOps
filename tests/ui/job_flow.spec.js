const { test, expect } = require('@playwright/test');

test.beforeEach(async ({ page }) => {
  await page.goto('/dev_login.php?role=dispatcher&loose=1');
  await expect(page.locator('body')).toContainText('"ok":true');
});

test('job creation flow with checklist and accessibility checks', async ({ page }) => {
  await page.goto('/add_job.php');

  const order = ['#customerId', '#description', '#status', '#scheduled_date', '#scheduled_time', '#duration_minutes'];
  await page.locator(order[0]).focus();
  for (let i = 1; i < order.length; i++) {
    await page.keyboard.press('Tab');
    await expect(page.locator(order[i])).toBeFocused();
  }

  await expect(page.locator('#customerId')).toHaveAttribute('aria-required', 'true');
  await page.selectOption('#customerId', { index: 1 });
  await page.fill('#description', 'UI Test Job');
  await page.selectOption('#status', 'scheduled');
  const today = new Date().toISOString().slice(0,10);
  await page.fill('#scheduled_date', today);
  await page.fill('#scheduled_time', '10:00');

  await page.click('#checklistModalLink');
  await page.click('#addChecklistItem');
  const itemInput = page.locator('#checklistModalBody .checklist-input').first();
  await itemInput.fill('First item');
  const removeBtn = page.locator('#checklistModalBody .checklist-item button').first();
  await expect(removeBtn).toHaveAttribute('aria-label', 'Remove item');
  await page.click('#saveChecklist');
  await expect(page.locator('#checklistHiddenInputs input')).toHaveCount(1);

  const saveButton = page.locator('button[type="submit"]');
  await saveButton.click();
  await expect(saveButton).toBeDisabled();
  const toast = page.locator('.toast-body');
  await expect(toast).toHaveText(/Job saved/i);
  await page.waitForURL('**/jobs.php');
});

test('handles server errors gracefully', async ({ page }) => {
  await page.route('**/job_save.php?json=1', route => {
    route.fulfill({
      status: 422,
      contentType: 'application/json',
      body: JSON.stringify({ ok: false, errors: ['Server validation failed'] })
    });
  });

  await page.goto('/add_job.php');
  await page.selectOption('#customerId', { index: 1 });
  await page.fill('#description', 'Bad Job');
  await page.selectOption('#status', 'scheduled');
  const today = new Date().toISOString().slice(0,10);
  await page.fill('#scheduled_date', today);
  await page.fill('#scheduled_time', '10:00');

  const saveButton = page.locator('button[type="submit"]');
  await saveButton.click();
  await expect(page.locator('#form-errors')).toContainText('Server validation failed');
  await expect(saveButton).toBeEnabled();
});
