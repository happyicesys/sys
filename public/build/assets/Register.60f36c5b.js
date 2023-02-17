import{u as p,o as f,c,w as i,a as s,b as a,H as _,d as t,L as w,n as V,e as b,f as d}from"./app.a63aabc8.js";import{_ as v}from"./Button.ff33d227.js";import{_ as g}from"./Guest.7629569a.js";import{_ as y,a as r,b as m}from"./ValidationErrors.f0952ed1.js";import"./_plugin-vue_export-helper.cdc0426e.js";const x=["onSubmit"],k={class:"mt-4"},U={class:"mt-4"},h={class:"mt-4"},$={class:"mt-4"},N={class:"flex items-center justify-end mt-4"},q=d(" Already registered? "),B=d(" Register "),S={__name:"Register",setup(C){const e=p({name:"",email:"",password:"",password_confirmation:"",username:"",terms:!1}),n=()=>{e.post(route("register"),{onFinish:()=>e.reset("password","password_confirmation")})};return(u,o)=>(f(),c(g,null,{default:i(()=>[s(a(_),{title:"Register"}),s(y,{class:"mb-4"}),t("form",{onSubmit:b(n,["prevent"])},[t("div",null,[s(r,{for:"name",value:"Name"}),s(m,{id:"name",type:"text",class:"mt-1 block w-full",modelValue:a(e).name,"onUpdate:modelValue":o[0]||(o[0]=l=>a(e).name=l),required:"",autofocus:"",autocomplete:"name"},null,8,["modelValue"])]),t("div",k,[s(r,{for:"email",value:"Email"}),s(m,{id:"email",type:"email",class:"mt-1 block w-full",modelValue:a(e).email,"onUpdate:modelValue":o[1]||(o[1]=l=>a(e).email=l),autocomplete:"email"},null,8,["modelValue"])]),t("div",U,[s(r,{for:"username",value:"Username"}),s(m,{id:"username",type:"text",class:"mt-1 block w-full",modelValue:a(e).username,"onUpdate:modelValue":o[2]||(o[2]=l=>a(e).username=l),autocomplete:"username"},null,8,["modelValue"])]),t("div",h,[s(r,{for:"password",value:"Password"}),s(m,{id:"password",type:"password",class:"mt-1 block w-full",modelValue:a(e).password,"onUpdate:modelValue":o[3]||(o[3]=l=>a(e).password=l),required:"",autocomplete:"new-password"},null,8,["modelValue"])]),t("div",$,[s(r,{for:"password_confirmation",value:"Confirm Password"}),s(m,{id:"password_confirmation",type:"password",class:"mt-1 block w-full",modelValue:a(e).password_confirmation,"onUpdate:modelValue":o[4]||(o[4]=l=>a(e).password_confirmation=l),required:"",autocomplete:"new-password"},null,8,["modelValue"])]),t("div",N,[s(a(w),{href:u.route("login"),class:"underline text-sm text-gray-600 hover:text-gray-900"},{default:i(()=>[q]),_:1},8,["href"]),s(v,{class:V(["ml-4",{"opacity-25":a(e).processing}]),disabled:a(e).processing},{default:i(()=>[B]),_:1},8,["class","disabled"])])],40,x)]),_:1}))}};export{S as default};