import{g as x,T as u,h as y,c,a as r,w as t,p as h,o as p,b as s,d,u as f,l as V,e as C}from"./app.6c1fd100.js";import{_ as v}from"./Button.c220b9da.js";import{_ as n}from"./FormInput.80b139a0.js";import{_ as k}from"./Modal.6dbafd54.js";import{r as w}from"./ArrowUturnLeftIcon.0e5d2ed7.js";import{r as M}from"./CheckCircleIcon.d3a38b3a.js";import"./keyboard.a01f6322.js";import"./disposables.3f9ca8af.js";const $=s("div",{class:"flex flex-col md:flex-row space-x-2"},[s("span",{class:"text-gray-600"}," Create New Machine ")],-1),B={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6 pb-2"},N={class:"sm:col-span-6"},S={class:"sm:col-span-6"},T={class:"sm:col-span-6"},U={class:"sm:col-span-6"},j={class:"flex space-x-1 mt-5 justify-end"},D=s("span",null," Back ",-1),q=s("span",null," Save ",-1),z={__name:"Create",props:{permissions:[Array,Object],type:String,showModal:Boolean},emits:["modalClose"],setup(l,{emit:_}){const g=_,e=x(u(i()));y(()=>{e.value=u(i())});function i(){return{begin_date:"",code:"",name:"",private_key:"",termination_date:""}}function b(){e.value.clearErrors(),e.value.post("/vends/create",{onSuccess:()=>{g("modalClose")},preserveState:!0,replace:!0})}return(m,a)=>(p(),c(h,{to:"body"},[r(k,{open:l.showModal,onModalClose:a[4]||(a[4]=o=>m.$emit("modalClose"))},{header:t(()=>[$]),default:t(()=>[s("form",{onSubmit:C(b,["prevent"]),id:"submit"},[s("div",B,[s("div",N,[r(n,{modelValue:e.value.code,"onUpdate:modelValue":a[0]||(a[0]=o=>e.value.code=o),error:e.value.errors.code,required:"true"},{default:t(()=>[d(" Machine ID ")]),_:1},8,["modelValue","error"])]),s("div",S,[r(n,{modelValue:e.value.name,"onUpdate:modelValue":a[1]||(a[1]=o=>e.value.name=o),error:e.value.errors.name},{default:t(()=>[d(" Name (Leave Blank if Bind from CMS) ")]),_:1},8,["modelValue","error"])]),s("div",T,[r(n,{modelValue:e.value.private_key,"onUpdate:modelValue":a[2]||(a[2]=o=>e.value.private_key=o),error:e.value.errors.private_key,disabled:!l.permissions.includes("update vends")},{default:t(()=>[d(" Private Key ")]),_:1},8,["modelValue","error","disabled"])])]),s("div",U,[s("div",j,[r(v,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:a[3]||(a[3]=o=>m.$emit("modalClose")),form:"submit"},{default:t(()=>[r(f(w),{class:"w-4 h-4"}),D]),_:1}),l.permissions.includes("update vends")?(p(),c(v,{key:0,type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:t(()=>[r(f(M),{class:"w-4 h-4"}),q]),_:1})):V("",!0)])])],32)]),_:1},8,["open"])]))}};export{z as default};
