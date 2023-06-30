import{T as u,c,w as m,o as f,a as o,u as e,Z as w,b as l,d as _,n as V,e as b}from"./app.5696c208.js";import{_ as k}from"./Button.05b52e08.js";import{_ as v}from"./Guest.06d4b775.js";import{_ as x,a as r,b as i}from"./ValidationErrors.fa7d0ebc.js";import"./_plugin-vue_export-helper.cdc0426e.js";const y=["onSubmit"],P={class:"mt-4"},$={class:"mt-4"},g={class:"flex items-center justify-end mt-4"},U={__name:"ResetPassword",props:{email:String,token:String},setup(n){const d=n,s=u({token:d.token,email:d.email,password:"",password_confirmation:""}),p=()=>{s.post(route("password.update"),{onFinish:()=>s.reset("password","password_confirmation")})};return(S,a)=>(f(),c(v,null,{default:m(()=>[o(e(w),{title:"Reset Password"}),o(x,{class:"mb-4"}),l("form",{onSubmit:b(p,["prevent"])},[l("div",null,[o(r,{for:"email",value:"Email"}),o(i,{id:"email",type:"email",class:"mt-1 block w-full",modelValue:e(s).email,"onUpdate:modelValue":a[0]||(a[0]=t=>e(s).email=t),required:"",autofocus:"",autocomplete:"username"},null,8,["modelValue"])]),l("div",P,[o(r,{for:"password",value:"Password"}),o(i,{id:"password",type:"password",class:"mt-1 block w-full",modelValue:e(s).password,"onUpdate:modelValue":a[1]||(a[1]=t=>e(s).password=t),required:"",autocomplete:"new-password"},null,8,["modelValue"])]),l("div",$,[o(r,{for:"password_confirmation",value:"Confirm Password"}),o(i,{id:"password_confirmation",type:"password",class:"mt-1 block w-full",modelValue:e(s).password_confirmation,"onUpdate:modelValue":a[2]||(a[2]=t=>e(s).password_confirmation=t),required:"",autocomplete:"new-password"},null,8,["modelValue"])]),l("div",g,[o(k,{class:V({"opacity-25":e(s).processing}),disabled:e(s).processing},{default:m(()=>[_(" Reset Password ")]),_:1},8,["class","disabled"])])],40,y)]),_:1}))}};export{U as default};
