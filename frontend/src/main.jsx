import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router'
import App from './App.jsx'
import { AuthProvider } from './context/AuthContext.jsx'
import { CartProvider } from './context/CartContext.jsx'
import { ConsentProvider } from './context/ConsentContext.jsx'
import { ShopProvider } from './context/ShopContext.jsx'
import './index.css'

// Provider order matters: the cart reads the quantity limit and delivery fee from
// the shop settings, and where it may persist from the visitor's cookie choice.
createRoot(document.getElementById('root')).render(
  <StrictMode>
    <BrowserRouter>
      <AuthProvider>
        <ConsentProvider>
          <ShopProvider>
            <CartProvider>
              <App />
            </CartProvider>
          </ShopProvider>
        </ConsentProvider>
      </AuthProvider>
    </BrowserRouter>
  </StrictMode>,
)
