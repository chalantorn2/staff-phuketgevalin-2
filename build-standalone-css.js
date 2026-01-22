import { readFileSync, writeFileSync } from 'fs';
import { resolve } from 'path';
import tailwindcss from '@tailwindcss/vite';
import postcss from 'postcss';

// Read the source CSS
const css = readFileSync(resolve('./tailwind.standalone.css'), 'utf8');

// Configure PostCSS with Tailwind
const processor = postcss([
  require('tailwindcss')
]);

async function build() {
  try {
    const result = await processor.process(css, {
      from: resolve('./tailwind.standalone.css'),
      to: resolve('./public/css/tailwind.min.css'),
    });

    writeFileSync(resolve('./public/css/tailwind.min.css'), result.css, 'utf8');
    console.log('✅ Tailwind CSS built successfully to public/css/tailwind.min.css');
  } catch (error) {
    console.error('❌ Build failed:', error);
    process.exit(1);
  }
}

build();
