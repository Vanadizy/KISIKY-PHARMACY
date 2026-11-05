import axios from 'axios';
export const api=axios.create({baseURL:import.meta.env.VITE_API_URL||'http://127.0.0.1:8000/api',withCredentials:true,headers:{'Content-Type':'application/json'}});
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
