module.exports = {
  tags: ["products"],
  permalink: (data) => `/products/${data.sku || data.page.fileSlug}/`,
  eleventyComputed: {
    active: (data) => (typeof data.active === "undefined" ? true : data.active),
  },
};
