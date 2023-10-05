import{T as d,c,w as i,o as r,a as s,u as t,Z as u,f as _,t as f,l as p,b as a,d as w,n as b,e as y}from"./app.a5ba100b.js";import{_ as g}from"./Button.b17e3b5e.js";import{_ as x}from"./Guest.c54ce298.js";import{_ as k,a as h,b as V}from"./ValidationErrors.930ce7f2.js";import"./_plugin-vue_export-helper.cdc0426e.js";const v=a("div",{class:"mb-4 text-sm text-gray-600"}," Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one. ",-1),N={key:0,class:"mb-4 font-medium text-sm text-green-600"},$=["onSubmit"],B={class:"flex items-center justify-end mt-4"},j={__name:"ForgotPassword",props:{status:String},setup(o){const e=d({email:""}),m=()=>{e.post(route("password.email"))};return(S,l)=>(r(),c(x,null,{default:i(()=>[s(t(u),{title:"Forgot Password"}),v,o.status?(r(),_("div",N,f(o.status),1)):p("",!0),s(k,{class:"mb-4"}),a("form",{onSubmit:y(m,["prevent"])},[a("div",null,[s(h,{for:"email",value:"Email"}),s(V,{id:"email",type:"email",class:"mt-1 block w-full",modelValue:t(e).email,"onUpdate:modelValue":l[0]||(l[0]=n=>t(e).email=n),required:"",autofocus:"",autocomplete:"username"},null,8,["modelValue"])]),a("div",B,[s(g,{class:b({"opacity-25":t(e).processing}),disabled:t(e).processing},{default:i(()=>[w(" Email Password Reset Link ")]),_:1},8,["class","disabled"])])],40,$)]),_:1}))}};export{j as default};
