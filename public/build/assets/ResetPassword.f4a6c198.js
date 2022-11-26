import{u,o as c,c as f,w as m,a as o,b as e,H as _,d as r,n as w,e as V,f as b}from"./app.c318dc46.js";import{_ as k}from"./Button.27f88839.js";import{_ as v}from"./Guest.c33a2c28.js";import{_ as x,a as l,b as i}from"./ValidationErrors.ba4cd75c.js";import"./_plugin-vue_export-helper.cdc0426e.js";const y=["onSubmit"],P={class:"mt-4"},$={class:"mt-4"},g={class:"flex items-center justify-end mt-4"},S=b(" Reset Password "),U={__name:"ResetPassword",props:{email:String,token:String},setup(n){const d=n,s=u({token:d.token,email:d.email,password:"",password_confirmation:""}),p=()=>{s.post(route("password.update"),{onFinish:()=>s.reset("password","password_confirmation")})};return(h,a)=>(c(),f(v,null,{default:m(()=>[o(e(_),{title:"Reset Password"}),o(x,{class:"mb-4"}),r("form",{onSubmit:V(p,["prevent"])},[r("div",null,[o(l,{for:"email",value:"Email"}),o(i,{id:"email",type:"email",class:"mt-1 block w-full",modelValue:e(s).email,"onUpdate:modelValue":a[0]||(a[0]=t=>e(s).email=t),required:"",autofocus:"",autocomplete:"username"},null,8,["modelValue"])]),r("div",P,[o(l,{for:"password",value:"Password"}),o(i,{id:"password",type:"password",class:"mt-1 block w-full",modelValue:e(s).password,"onUpdate:modelValue":a[1]||(a[1]=t=>e(s).password=t),required:"",autocomplete:"new-password"},null,8,["modelValue"])]),r("div",$,[o(l,{for:"password_confirmation",value:"Confirm Password"}),o(i,{id:"password_confirmation",type:"password",class:"mt-1 block w-full",modelValue:e(s).password_confirmation,"onUpdate:modelValue":a[2]||(a[2]=t=>e(s).password_confirmation=t),required:"",autocomplete:"new-password"},null,8,["modelValue"])]),r("div",g,[o(k,{class:w({"opacity-25":e(s).processing}),disabled:e(s).processing},{default:m(()=>[S]),_:1},8,["class","disabled"])])],40,y)]),_:1}))}};export{U as default};
