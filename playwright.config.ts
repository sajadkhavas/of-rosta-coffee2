import { defineConfig } from "@playwright/test";

export default defineConfig({
  testDir: "./tests/browser",
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 60_000,
  expect: {
    timeout: 10_000,
  },
  reporter: [
    ["line"],
    ["junit", { outputFile: process.env.R3C_JUNIT_PATH ?? "test-results/r3c-junit.xml" }],
  ],
  use: {
    baseURL: process.env.R3C_FRONTEND_BASE ?? "http://127.0.0.1:3000",
    locale: "fa-IR",
    timezoneId: "Asia/Tehran",
    colorScheme: "dark",
    reducedMotion: "reduce",
    trace: "off",
    screenshot: "off",
    video: "off",
  },
  projects: [
    {
      name: "chromium",
      use: {
        browserName: "chromium",
        viewport: { width: 1440, height: 1000 },
      },
    },
  ],
});
