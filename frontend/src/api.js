import axios from 'axios';
// In production, use the same-origin API by default. VITE_API_URL can override
// this at build time when the API is hosted on a separate domain or path.
const apiBaseUrl=import.meta.env.VITE_API_URL||(import.meta.env.PROD?`${window.location.origin}/api`:'http://127.0.0.1:8000/api');
export const api=axios.create({baseURL:apiBaseUrl,withCredentials:true,headers:{'Content-Type':'application/json'}});
let csrf='';
api.interceptors.request.use(config=>{if(csrf)config.headers['X-CSRF-Token']=csrf;return config;});
export function setCsrf(value){csrf=value||'';}

api.interceptors.response.use(response=>response,error=>{
  const url=String(error?.config?.url||'');
  const expectedAnonymousSessionCheck=url.endsWith('/auth/me')&&error?.response?.status===401;
  if(!expectedAnonymousSessionCheck){
    const message=error?.response?.data?.error||error?.message||'The request could not be completed.';
    window.dispatchEvent(new CustomEvent('pharmacy:notify',{detail:{message,type:'error'}}));
  }
  return Promise.reject(error);
});
