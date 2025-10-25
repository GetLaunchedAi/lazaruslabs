// Simple cart state using localStorage
const cart = JSON.parse(localStorage.getItem('cart')) || [];

function addToCart(product) {
  const existing = cart.find(p => p.id === product.id);
  if (existing) {
    console.log("Product exists in cart, updating quantity:", existing, "New quantity:", existing.quantity + product.quantity);
    existing.quantity += product.quantity;
  } else {
    cart.push(product);
  }
  localStorage.setItem('cart', JSON.stringify(cart));
  updateCartCount();
}

function removeFromCart(id) {
  const index = cart.findIndex(p => p.id === id);
  if (index > -1) cart.splice(index, 1);
  localStorage.setItem('cart', JSON.stringify(cart));
  updateCartCount();
}

function updateCartCount() {
  document.querySelector('.cart-count').textContent =
    cart.reduce((sum, p) => sum + p.quantity, 0);
}
