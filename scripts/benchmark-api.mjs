
/**
 * StyleHub Local API Performance Benchmark
 *
 * Run:
 * node scripts/benchmark-api.mjs
 *
 * Requirements:
 * Node.js 18 or newer
 *
 * Safety:
 * - Local/private HTTP targets only
 * - GET requests only
 * - No database modifications
 * - No real SMS
 * - Maximum 5 concurrent requests
 */

import { performance } from 'node:perf_hooks';

/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

const rawBase =
  process.env.STYLEHUB_BENCH_URL ||
  'http://127.0.0.1:8000';

const base = new URL(rawBase);

const concurrencyLevels = [1, 3, 5];

const requestsPerLevel = 15;

const timeoutMs = 8000;

/*
|--------------------------------------------------------------------------
| Local Network Safety
|--------------------------------------------------------------------------
*/

function isPrivateHost(hostname) {
  const host = hostname
    .toLowerCase()
    .replace(/^\[|\]$/g, '');

  if (
    host === 'localhost' ||
    host === '127.0.0.1' ||
    host === '::1'
  ) {
    return true;
  }

  if (
    /^192\.168\.\d{1,3}\.\d{1,3}$/.test(host)
  ) {
    return true;
  }

  if (
    /^10\.\d{1,3}\.\d{1,3}\.\d{1,3}$/.test(host)
  ) {
    return true;
  }

  const match =
    /^172\.(\d{1,3})\.\d{1,3}\.\d{1,3}$/
      .exec(host);

  return Boolean(
    match &&
    Number(match[1]) >= 16 &&
    Number(match[1]) <= 31
  );
}

if (
  base.protocol !== 'http:' ||
  !isPrivateHost(base.hostname) ||
  base.username ||
  base.password ||
  base.pathname !== '/' ||
  base.search ||
  base.hash
) {
  console.error(
    'Use a local/private HTTP server origin only.'
  );

  console.error(
    'Example: http://127.0.0.1:8000'
  );

  process.exit(1);
}

const origin = base.origin;

/*
|--------------------------------------------------------------------------
| Optional Test Account Tokens
|--------------------------------------------------------------------------
|
| Set environment variables only if you want
| to measure protected appointment endpoints.
|
| Do not paste access tokens into this file.
|
*/

function getTokens(name) {
  return (
    process.env[name] || ''
  )
    .split(',')
    .map((value) => value.trim())
    .filter(Boolean);
}

/*
|--------------------------------------------------------------------------
| Read-only Test Endpoints
|--------------------------------------------------------------------------
*/

const testCases = [
  {
    label: 'Laravel health',

    path: '/up',

    tokens: [],

    requiresAuth: false,
  },

  {
    label: 'Customer appointments',

    path:
      '/api/v1/customer/appointments' +
      '?page=1&per_page=20',

    tokens: getTokens(
      'STYLEHUB_CUSTOMER_TOKENS'
    ),

    requiresAuth: true,
  },

  {
    label: 'Shop-owner appointments',

    path:
      '/api/v1/shop-owner/appointments' +
      '?page=1&per_page=20',

    tokens: getTokens(
      'STYLEHUB_OWNER_TOKENS'
    ),

    requiresAuth: true,
  },

  {
    label: 'Admin appointments',

    path:
      '/api/v1/admin/appointments' +
      '?page=1&per_page=20',

    tokens: getTokens(
      'STYLEHUB_ADMIN_TOKENS'
    ),

    requiresAuth: true,
  },
];

/*
|--------------------------------------------------------------------------
| HTTP Request Measurement
|--------------------------------------------------------------------------
*/

async function measureRequest(path, token) {
  const startedAt = performance.now();

  try {
    const response = await fetch(
      new URL(path, origin),
      {
        method: 'GET',

        redirect: 'error',

        signal: AbortSignal.timeout(
          timeoutMs
        ),

        headers: {
          Accept: 'application/json',

          ...(token
            ? {
                Authorization:
                  `Bearer ${token}`,
              }
            : {}),
        },
      }
    );

    const body =
      await response.arrayBuffer();

    const duration =
      performance.now() - startedAt;

    return {
      duration,

      status: response.status,

      bytes: body.byteLength,

      success: response.ok,
    };
  } catch (error) {
    return {
      duration:
        performance.now() - startedAt,

      status: 0,

      bytes: 0,

      success: false,

      reason:
        error?.name || 'NetworkError',
    };
  }
}

/*
|--------------------------------------------------------------------------
| Concurrent Benchmark
|--------------------------------------------------------------------------
*/

async function runBenchmark(
  test,
  concurrency
) {
  const results = new Array(
    requestsPerLevel
  );

  let nextIndex = 0;

  const startedAt = performance.now();

  async function worker() {
    while (
      nextIndex < requestsPerLevel
    ) {
      const index = nextIndex++;

      const token =
        test.tokens.length > 0
          ? test.tokens[
              index % test.tokens.length
            ]
          : null;

      results[index] =
        await measureRequest(
          test.path,
          token
        );
    }
  }

  await Promise.all(
    Array.from(
      { length: concurrency },
      () => worker()
    )
  );

  const elapsedSeconds =
    (
      performance.now() - startedAt
    ) / 1000;

  const successful = results.filter(
    (result) => result.success
  );

  const durations = successful
    .map((result) => result.duration)
    .sort((a, b) => a - b);

  /*
  |--------------------------------------------------------------------------
  | Percentile Calculation
  |--------------------------------------------------------------------------
  */

  function percentile(value) {
    if (durations.length === 0) {
      return '-';
    }

    const index =
      Math.ceil(
        durations.length * value
      ) - 1;

    return durations[index]
      .toFixed(0);
  }

  /*
  |--------------------------------------------------------------------------
  | HTTP Status Summary
  |--------------------------------------------------------------------------
  */

  const statuses = {};

  for (const result of results) {
    const status =
      result.status ||
      result.reason ||
      'NetworkError';

    statuses[status] =
      (statuses[status] || 0) + 1;
  }

  /*
  |--------------------------------------------------------------------------
  | Average Response Size
  |--------------------------------------------------------------------------
  */

  const averageSize =
    successful.length > 0
      ? (
          successful.reduce(
            (total, result) =>
              total + result.bytes,
            0
          ) /
          successful.length /
          1024
        ).toFixed(1)
      : '-';

  /*
  |--------------------------------------------------------------------------
  | Requests per Second
  |--------------------------------------------------------------------------
  */

  const requestsPerSecond =
    (
      successful.length /
      Math.max(
        elapsedSeconds,
        0.001
      )
    ).toFixed(1);

  console.log(
    `  Concurrent requests: ${concurrency}`
  );

  console.log(
    `  Successful: ${successful.length}/${requestsPerLevel}`
  );

  console.log(
    `  Median (p50): ${percentile(0.50)} ms`
  );

  console.log(
    `  Slowest 5% boundary (p95): ${percentile(0.95)} ms`
  );

  console.log(
    `  Throughput: ${requestsPerSecond} req/sec`
  );

  console.log(
    `  Average response: ${averageSize} KB`
  );

  console.log(
    `  HTTP statuses: ${JSON.stringify(statuses)}`
  );

  console.log('');

  return (
    successful.length ===
    requestsPerLevel
  );
}

/*
|--------------------------------------------------------------------------
| Run Tests
|--------------------------------------------------------------------------
*/

console.log(
  '======================================'
);

console.log(
  ' STYLEHUB API PERFORMANCE TEST'
);

console.log(
  '======================================'
);

console.log(
  `Server: ${origin}`
);

console.log(
  'Requests per concurrency level: 15'
);

console.log(
  'Concurrency levels: 1, 3, 5'
);

console.log(
  'Request timeout: 8000 ms'
);

console.log('');

console.log(
  'Checking Laravel connectivity...'
);

const health = await measureRequest(
  '/up',
  null
);

if (!health.success) {
  console.error(
    'Laravel could not be reached.'
  );

  console.error(
    `HTTP: ${health.status || 'Network error'}`
  );

  console.error(
    'Start your Laravel server and try again.'
  );

  process.exit(1);
}

console.log(
  'Laravel is reachable.'
);

let allPassed = true;

let authenticatedTests = 0;

for (const test of testCases) {
  console.log('');

  console.log(
    `TEST: ${test.label}`
  );

  if (
    test.requiresAuth &&
    test.tokens.length === 0
  ) {
    console.log(
      'SKIPPED: No authentication token supplied.'
    );

    continue;
  }

  if (test.requiresAuth) {
    authenticatedTests++;

    console.log(
      `Test tokens: ${test.tokens.length}`
    );
  }

  for (
    const concurrency
    of concurrencyLevels
  ) {
    const passed =
      await runBenchmark(
        test,
        concurrency
      );

    if (!passed) {
      allPassed = false;
    }
  }
}

/*
|--------------------------------------------------------------------------
| Final Summary
|--------------------------------------------------------------------------
*/

console.log(
  '======================================'
);

console.log(
  ' PERFORMANCE TEST COMPLETED'
);

console.log(
  '======================================'
);

if (allPassed) {
  console.log(
    'All executed requests succeeded.'
  );
} else {
  console.log(
    'Some requests failed. Review the HTTP statuses.'
  );

  process.exitCode = 1;
}

if (authenticatedTests === 0) {
  console.log(
    'Appointment performance was NOT measured.'
  );

  console.log(
    'Provide test-account tokens to enable protected API tests.'
  );
}

console.log(
  'This is a small local performance check.'
);

console.log(
  'It does not certify production capacity or security.'
);

console.log(
  'php artisan serve may serialize concurrent requests.'
);
