import{T as l,c as d,w as t,o as m,a,u as o,Z as c,b as e,d as f,n as p,e as u}from"./app.6c1fd100.js";import{_}from"./Button.c220b9da.js";import{_ as w}from"./Guest.a15ece67.js";import{_ as b,a as x,b as h}from"./ValidationErrors.ad71ec0a.js";import"./_plugin-vue_export-helper.cdc0426e.js";const V=e("div",{class:"mb-4 text-sm text-gray-600"}," This is a secure area of the application. Please confirm your password before continuing. ",-1),v={class:"flex justify-end mt-4"},N={__name:"ConfirmPassword",setup(y){const s=l({password:""}),i=()=>{s.post(route("password.confirm"),{onFinish:()=>s.reset()})};return(C,r)=>(m(),d(w,null,{default:t(()=>[a(o(c),{title:"Confirm Password"}),V,a(b,{class:"mb-4"}),e("form",{onSubmit:u(i,["prevent"])},[e("div",null,[a(x,{for:"password",value:"Password"}),a(h,{id:"password",type:"password",class:"mt-1 block w-full",modelValue:o(s).password,"onUpdate:modelValue":r[0]||(r[0]=n=>o(s).password=n),required:"",autocomplete:"current-password",autofocus:""},null,8,["modelValue"])]),e("div",v,[a(_,{class:p(["ml-4",{"opacity-25":o(s).processing}]),disabled:o(s).processing},{default:t(()=>[f(" Confirm ")]),_:1},8,["class","disabled"])])],32)]),_:1}))}};export{N as default};
