import{T as d,c as l,w as t,o as m,a as o,u as a,Z as c,b as e,d as f,n as p,e as u}from"./app.a5ba100b.js";import{_}from"./Button.b17e3b5e.js";import{_ as w}from"./Guest.c54ce298.js";import{_ as b,a as h,b as x}from"./ValidationErrors.930ce7f2.js";import"./_plugin-vue_export-helper.cdc0426e.js";const V=e("div",{class:"mb-4 text-sm text-gray-600"}," This is a secure area of the application. Please confirm your password before continuing. ",-1),v=["onSubmit"],y={class:"flex justify-end mt-4"},T={__name:"ConfirmPassword",setup(C){const s=d({password:""}),i=()=>{s.post(route("password.confirm"),{onFinish:()=>s.reset()})};return($,r)=>(m(),l(w,null,{default:t(()=>[o(a(c),{title:"Confirm Password"}),V,o(b,{class:"mb-4"}),e("form",{onSubmit:u(i,["prevent"])},[e("div",null,[o(h,{for:"password",value:"Password"}),o(x,{id:"password",type:"password",class:"mt-1 block w-full",modelValue:a(s).password,"onUpdate:modelValue":r[0]||(r[0]=n=>a(s).password=n),required:"",autocomplete:"current-password",autofocus:""},null,8,["modelValue"])]),e("div",y,[o(_,{class:p(["ml-4",{"opacity-25":a(s).processing}]),disabled:a(s).processing},{default:t(()=>[f(" Confirm ")]),_:1},8,["class","disabled"])])],40,v)]),_:1}))}};export{T as default};
