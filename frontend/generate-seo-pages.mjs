import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const rootUrl = new URL('./dist/', import.meta.url);
const root = fileURLToPath(rootUrl);
const source = await readFile(new URL('index.html', rootUrl), 'utf8');
const pharmacy = 'Kisiky Pharmacy';
const pages = [
  { path: '/products', title: 'Medicines and Health Products', description: 'Browse Kisiky Pharmacy medicines, health products and everyday pharmacy essentials. View product information and prepare an order for your preferred branch.' },
  { path: '/order-now', title: 'Order Medicines Online', description: 'Browse pharmacy products, choose a Kisiky Pharmacy branch and submit an order for availability review.' },
  { path: '/locations', title: 'Pharmacy Branch Locations', description: 'Find Kisiky Pharmacy branch addresses, areas, phone numbers, email contacts and map directions in Tanzania.' },
  { path: '/about', title: 'About Kisiky Pharmacy', description: 'Learn about Kisiky Pharmacy, our pharmacy team, services and the communities served by our branches.' },
  { path: '/customer', title: 'Customer Account', description: 'Sign in or create a customer account to place and manage pharmacy orders.', private: true },
  { path: '/my-orders', title: 'My Orders', description: 'Review private pharmacy order status, payment updates and invoices.', private: true },
];

function setMeta(html, selector, replacement) {
  const re = new RegExp(`<meta ${selector} content="[^"]*"\\s*/?>`);
  if (!re.test(html)) throw new Error(`Expected metadata tag not found: ${selector}`);
  return html.replace(re, replacement);
}

for (const page of pages) {
  const canonical = `https://kisikypharmacy.com${page.path}`;
  const title = `${page.title} | ${pharmacy}`;
  let html = source;
  html = html.replace(/<title>[^<]*<\/title>/, `<title>${title}</title>`);
  html = setMeta(html, 'name="description"', `<meta name="description" content="${page.description}" />`);
  html = setMeta(html, 'name="robots"', `<meta name="robots" content="${page.private ? 'noindex,nofollow' : 'index,follow,max-image-preview:large'}" />`);
  html = setMeta(html, 'name="googlebot"', `<meta name="googlebot" content="${page.private ? 'noindex,nofollow' : 'index,follow,max-image-preview:large'}" />`);
  html = setMeta(html, 'property="og:url"', `<meta property="og:url" content="${canonical}" />`);
  html = setMeta(html, 'property="og:title"', `<meta property="og:title" content="${title}" />`);
  html = setMeta(html, 'property="og:description"', `<meta property="og:description" content="${page.description}" />`);
  html = setMeta(html, 'name="twitter:title"', `<meta name="twitter:title" content="${title}" />`);
  html = setMeta(html, 'name="twitter:description"', `<meta name="twitter:description" content="${page.description}" />`);
  html = html.replace('href="https://kisikypharmacy.com/" />', `href="${canonical}" />`);
  const target = join(root, page.path.slice(1), 'index.html');
  await mkdir(dirname(target), { recursive: true });
  await writeFile(target, html, 'utf8');
}
console.log(`Generated ${pages.length} route-specific metadata pages.`);
