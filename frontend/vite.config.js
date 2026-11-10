import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

const seoRoutes = new Set(['/products', '/order-now', '/locations', '/about', '/customer', '/my-orders']);
const seoPagePreview = {
  name: 'seo-page-preview',
  configurePreviewServer(server) {
    server.middlewares.use((req, res, next) => {
      if (req.method === 'GET' && req.url) {
        const url = new URL(req.url, 'http://localhost');
        if (seoRoutes.has(url.pathname)) req.url = `${url.pathname}/index.html${url.search}`;
      }
      next();
    });
  },
};

export default defineConfig({ plugins: [react(), seoPagePreview], server: { host: '0.0.0.0', port: 5173 } });
