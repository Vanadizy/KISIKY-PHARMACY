import axios from 'axios';
export const api=axios.create({baseURL:import.meta.env.VITE_API_URL||'http://127.0.0.1:8000/api',withCredentials:true,headers:{'Content-Type':'application/json'}});
let csrf='';
api.interceptors.request.use(config=>{if(csrf)config.headers['X-CSRF-Token']=csrf;return config;});
export function setCsrf(value){csrf=value||'';}
