import client from './client'

// Each returns { user, token }.
export const login = (credentials) => client.post('/login', credentials).then((r) => r.data.data)

export const register = (payload) => client.post('/register', payload).then((r) => r.data.data)

export const registerAdmin = (payload) => client.post('/admin/register', payload).then((r) => r.data.data)

export const logout = () => client.post('/logout')

export const me = (options) => client.get('/me', options).then((r) => r.data.data)
