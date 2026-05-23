import axios from 'axios';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost:3000/api';

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Auth endpoints
const authApi = {
  login: (credentials) => api.post('/auth/login', credentials),
  register: (userData) => api.post('/auth/register', userData),
};

// Reservation endpoints
const reservationApi = {
  create: (reservationData, token) => 
    api.post('/reservations', reservationData, {
      headers: { Authorization: `Bearer ${token}` },
    }),
  getUserReservations: (token) => 
    api.get('/reservations', {
      headers: { Authorization: `Bearer ${token}` },
    }),
  cancel: (reservationId, token) => 
    api.put(`/reservations/${reservationId}/cancel`, {}, {
      headers: { Authorization: `Bearer ${token}` },
    }),
};

// Menu endpoints
const menuApi = {
  getAll: () => api.get('/menu'),
  getByCategory: (category) => api.get('/menu', { params: { category } }),
  getById: (id) => api.get(`/menu/${id}`),
};

// Testimonials endpoints
const testimonialApi = {
  getAll: () => api.get('/testimonials'),
};

// Gallery endpoints
const galleryApi = {
  getAll: () => api.get('/gallery'),
};

export const fetchMenuItems = async () => {
  const res = await menuApi.getAll();
  return res.data;
};

export {
  authApi,
  reservationApi,
  menuApi,
  testimonialApi,
  galleryApi,
  api as default,
};