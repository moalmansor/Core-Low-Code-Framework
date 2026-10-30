// Load test of architecture §20.2: the request budgets at p95 under the
// concurrency budget (≈ 50 requests per second sustained, 150 at peak, set by
// LCF_RATE and LCF_PEAK_RATE) with an error rate below 0.1 %. Run against the
// Docker stack after the dataset of backend/tests/Performance has been built:
//
//   k6 run -e LCF_URL=http://localhost:8080 -e LCF_TARGETS=perf-targets.json \
//          -e LCF_K6_OUT=k6-summary.json perf/k6/load.js
//
// People sign in once each in setup() (sign-in is limited to 30 a minute per
// address, so it is paced), then every virtual user works in one of those
// sessions, as the SPA does: session cookie, XSRF header, same origin.

import http from "k6/http";
import { check, fail, sleep } from "k6";
import exec from "k6/execution";
import { Rate, Trend } from "k6/metrics";

const BASE = __ENV.LCF_URL || "http://localhost:8080";
const targets = JSON.parse(open(__ENV.LCF_TARGETS || "perf-targets.json"));
const SESSIONS = Math.min(
  Number(__ENV.LCF_SESSIONS || 100),
  targets.users.pool.length,
);
const SUSTAINED = __ENV.LCF_SUSTAINED || "5m";
const PEAK = __ENV.LCF_PEAK || "2m";
// Arrival rates: §20.2's 50/s sustained and 150/s peak are for the reference
// environment of §20.1; a smaller machine is loaded in proportion (docs/performance.md).
const RATE = Number(__ENV.LCF_RATE || 50);
const PEAK_RATE = Number(__ENV.LCF_PEAK_RATE || 150);

const trends = {
  definition: new Trend("lcf_definition", true),
  record_view: new Trend("lcf_record_view", true),
  record_open: new Trend("lcf_record_open", true),
  record_list: new Trend("lcf_record_list", true),
  global_search: new Trend("lcf_global_search", true),
  record_save: new Trend("lcf_record_save", true),
};
const errors = new Rate("lcf_errors");

export const options = {
  setupTimeout: "15m",
  discardResponseBodies: false,
  scenarios: {
    sustained: {
      executor: "constant-arrival-rate",
      rate: RATE,
      timeUnit: "1s",
      duration: SUSTAINED,
      preAllocatedVUs: Math.max(10, RATE * 2),
      maxVUs: RATE * 10,
      exec: "mix",
    },
    peak: {
      executor: "constant-arrival-rate",
      rate: PEAK_RATE,
      timeUnit: "1s",
      duration: PEAK,
      startTime: SUSTAINED,
      preAllocatedVUs: Math.max(30, PEAK_RATE * 2),
      maxVUs: Math.max(100, PEAK_RATE * 7),
      exec: "mix",
    },
  },
  thresholds: {
    lcf_errors: ["rate<0.001"],
    lcf_definition: ["p(95)<150"],
    lcf_record_view: ["p(95)<400"],
    lcf_record_open: ["p(95)<400"],
    lcf_record_list: ["p(95)<500"],
    lcf_global_search: ["p(95)<800"],
    lcf_record_save: ["p(95)<400"],
    // Not budgets: these only make k6 keep each stage's own figures for the summary below.
    ...Object.fromEntries(
      [
        "definition",
        "record_view",
        "record_open",
        "record_list",
        "global_search",
        "record_save",
      ].flatMap((k) =>
        ["sustained", "peak"].map((st) => [
          `lcf_${k}{stage:${st}}`,
          ["p(95)>=0"],
        ]),
      ),
    ),
  },
  summaryTrendStats: ["avg", "med", "p(90)", "p(95)", "p(99)", "max", "count"],
};

function xsrf(jar) {
  const c = jar.cookiesForURL(BASE)["XSRF-TOKEN"];
  return c && c.length ? decodeURIComponent(c[0]) : "";
}

function headers(jar) {
  return {
    Accept: "application/json",
    "Content-Type": "application/json",
    "X-Requested-With": "XMLHttpRequest",
    "X-XSRF-TOKEN": xsrf(jar),
    Origin: BASE,
    Referer: `${BASE}/`,
  };
}

export function setup() {
  const jar = http.cookieJar();
  const sessions = [];
  for (let i = 0; i < SESSIONS; i++) {
    const person = targets.users.pool[i];
    jar.clear(BASE);
    http.get(`${BASE}/sanctum/csrf-cookie`, {
      headers: { Origin: BASE, Referer: `${BASE}/` },
    });
    const res = http.post(
      `${BASE}/api/v1/auth/login`,
      JSON.stringify({ email: person.email, password: targets.users.password }),
      { headers: headers(jar) },
    );
    if (res.status !== 200)
      fail(`sign-in of ${person.email} answered ${res.status}: ${res.body}`);
    sessions.push({ root: person.root, cookies: jar.cookiesForURL(BASE) });
    // Sign-in allows 30 attempts a minute from one address.
    sleep(2.1);
  }
  return { sessions };
}

function use(data) {
  const session = data.sessions[(__VU - 1) % data.sessions.length];
  const jar = http.cookieJar();
  for (const [name, values] of Object.entries(session.cookies))
    jar.set(BASE, name, values[0]);
  return { session, jar };
}

const pick = (list) => list[Math.floor(Math.random() * list.length)];

function record(trend, res, ok) {
  const passed = check(res, { [`${trend.name} answered`]: () => ok });
  errors.add(!passed);
  if (passed) trend.add(res.timings.duration, { stage: exec.scenario.name });
  return passed;
}

function orderValues() {
  const n = Math.floor(Math.random() * 1e9);
  const lines = [];
  for (let i = 0; i < 5; i++)
    lines.push({
      item: `Item ${n}-${i}`,
      qty: (i % 4) + 1,
      unit_price: String(10 + i),
      line_date: "2026-10-01",
    });
  return {
    subject: `Load order ${n}`,
    reference: `LT-${n}`,
    contact_name: "Load Test",
    contact_phone: "+966500000000",
    contact_email: `lt${n}@example.test`,
    city: "Riyadh",
    street: "King Fahd Road",
    note: "load",
    description: "Created by the load test.",
    internal_notes: "n/a",
    delivery_notes: "n/a",
    terms: "n/a",
    quantity: 3,
    weight: 12.5,
    volume: 1.2,
    discount: 5,
    score: 7,
    items: 5,
    amount: "1250.50",
    tax: "187.58",
    shipping: "25.00",
    cost: "900.00",
    paid: "0",
    balance: "1463.08",
    order_date: "2026-10-01",
    due_date: "2026-10-15",
    ship_date: "2026-10-03",
    invoice_date: "2026-10-02",
    close_date: "2026-10-20",
    // Date-times carry their offset: the API refuses a local time without one.
    received_at: "2026-10-01T09:30:00Z",
    confirmed_at: "2026-10-01T10:00:00Z",
    urgent: true,
    gift: false,
    insured: true,
    channel: "web",
    payment_method: "card",
    zone: "central",
    customer: pick(targets.lookups.customers),
    product: pick(targets.lookups.products),
    region: pick(targets.lookups.regions),
    lines,
    payments: [],
  };
}

export function mix(data) {
  const { session, jar } = use(data);
  const h = { headers: headers(jar) };
  const f = targets.forms;
  const roll = Math.random();

  if (roll < 0.3) {
    // 25 rows, ten columns (two through lookups), three filters, a sort, within the record scope.
    const q = `view=${targets.view}&per_page=25&sort=amount&direction=desc&filter[channel][op]=in&filter[channel][values][]=phone&filter[urgent]=1&filter[order_date][from]=2025-06-01&filter[order_date][to]=2026-12-31`;
    const res = http.get(
      `${BASE}/api/v1/r/${f.order}?${q}`,
      Object.assign({ tags: { name: "list" } }, h),
    );
    record(trends.record_list, res, res.status === 200);
  } else if (roll < 0.5) {
    // A customer's page: the record and its three panels (region, its orders, their total), loaded together.
    const uuid = pick(targets.samples.customer);
    const [rec, panels] = http.batch([
      [
        "GET",
        `${BASE}/api/v1/r/${f.customer}/${uuid}`,
        null,
        Object.assign({ tags: { name: "record" } }, h),
      ],
      [
        "GET",
        `${BASE}/api/v1/r/${f.customer}/${uuid}/panels`,
        null,
        Object.assign({ tags: { name: "panels" } }, h),
      ],
    ]);
    const slowest =
      rec.timings.duration > panels.timings.duration ? rec : panels;
    record(
      trends.record_view,
      slowest,
      rec.status === 200 && panels.status === 200,
    );
  } else if (roll < 0.65) {
    const uuid = pick(targets.samples.order[session.root]);
    const res = http.get(
      `${BASE}/api/v1/r/${f.order}/${uuid}`,
      Object.assign({ tags: { name: "order" } }, h),
    );
    record(trends.record_open, res, res.status === 200);
  } else if (roll < 0.75) {
    const res = http.get(
      `${BASE}/api/v1/r/${f.order}/definition`,
      Object.assign({ tags: { name: "definition" } }, h),
    );
    record(trends.definition, res, res.status === 200);
  } else if (roll < 0.85) {
    const res = http.get(
      `${BASE}/api/v1/search?q=${encodeURIComponent(pick(targets.search_terms))}`,
      Object.assign({ tags: { name: "search" } }, h),
    );
    record(trends.global_search, res, res.status === 200);
  } else {
    // A new order with one repeating section of five rows, then (every third time) an edit of it.
    const res = http.post(
      `${BASE}/api/v1/r/${f.order}`,
      JSON.stringify({ values: orderValues() }),
      Object.assign({ tags: { name: "create" } }, h),
    );
    if (
      record(trends.record_save, res, res.status === 201) &&
      Math.random() < 0.34
    ) {
      const created = res.json("data");
      const upd = http.patch(
        `${BASE}/api/v1/r/${f.order}/${created.uuid}`,
        JSON.stringify({
          values: { amount: "1300.00", note: "edited" },
          row_version: created.row_version,
        }),
        Object.assign({ tags: { name: "update" } }, h),
      );
      record(trends.record_save, upd, upd.status === 200);
    }
  }
}

export function handleSummary(data) {
  const out = {};
  if (__ENV.LCF_K6_OUT) out[__ENV.LCF_K6_OUT] = JSON.stringify(data, null, 2);
  out.stdout =
    Object.entries(trends)
      .map(([key, t]) => {
        const v = data.metrics[t.name]?.values;
        const stage = (st) => {
          const sv = data.metrics[`${t.name}{stage:${st}}`]?.values;
          return sv
            ? `${st} ${sv["p(95)"].toFixed(1)} ms (n=${sv.count})`
            : `${st} -`;
        };
        return v
          ? `${key.padEnd(14)} p95 ${v["p(95)"].toFixed(1)} ms  (n=${v.count});  ${stage("sustained")};  ${stage("peak")}`
          : `${key.padEnd(14)} no samples`;
      })
      .concat([
        `errors         ${((data.metrics.lcf_errors?.values.rate ?? 0) * 100).toFixed(3)} %`,
        `requests/s     ${(data.metrics.http_reqs?.values.rate ?? 0).toFixed(1)}`,
      ])
      .join("\n") + "\n";
  return out;
}
