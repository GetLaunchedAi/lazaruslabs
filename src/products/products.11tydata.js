// src/products/products.11tydata.js
module.exports = {
  layout: 'product.njk',
  tags: ['products'],
  permalink: data => `/products/${data.sku || data.page.fileSlug}/`,
};
