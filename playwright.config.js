const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/ui',
  use: {
    baseURL: process.env.BASE_URL || 'http://127.0.0.1:8000',
    headless: true,
  },
  webServer: {
    command: 'php -S 127.0.0.1:8000 -t public',
    url: 'http://127.0.0.1:8000/login.php',
    reuseExistingServer: !process.env.CI,
    env: {
      DB_HOST: process.env.DB_HOST || '127.0.0.1',
      DB_PORT: process.env.DB_PORT || '3306',
      DB_NAME: process.env.DB_NAME || 'fieldops_integration',
      DB_USER: process.env.DB_USER || 'root',
      DB_PASS: process.env.DB_PASS || '1234!@#$',
      APP_ENV: 'test',
      FIELDOPS_TEST_DSN: process.env.FIELDOPS_TEST_DSN || 'sqlite:/tmp/fieldops_test.db'
    }
  }
});
