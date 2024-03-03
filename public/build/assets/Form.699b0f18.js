import{g as h,T as m,h as b,c as C,a,w as r,p as w,o as n,b as o,f as d,l as V,t as k,d as f,u as _,e as S}from"./app.6c1fd100.js";import{_ as v}from"./Button.c220b9da.js";import{_ as g}from"./FormInput.80b139a0.js";import{_ as $}from"./Modal.6dbafd54.js";import{r as B}from"./ArrowUturnLeftIcon.0e5d2ed7.js";import{r as M}from"./CheckCircleIcon.d3a38b3a.js";import"./keyboard.a01f6322.js";import"./disposables.3f9ca8af.js";const N={class:"flex flex-col md:flex-row space-x-2"},E={key:0,class:"text-gray-600"},T={key:1},U={key:2,class:"text-gray-600"},j={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},D={class:"sm:col-span-6"},F={class:"sm:col-span-6"},O={class:"sm:col-span-6"},q={class:"flex space-x-1 mt-5 justify-end"},H=o("span",null," Back ",-1),z=o("span",null," Save ",-1),R={__name:"Form",props:{uom:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(i,{emit:x}){const t=i,u=x,e=h(m(c()));b(()=>{e.value=t.uom?m(t.uom):m(c())});function c(){return{name:"",color:""}}function y(){e.value.clearErrors(),t.type==="create"&&e.value.post("/uoms/create",{onSuccess:()=>{u("modalClose")},preserveState:!0,replace:!0}),t.type==="update"&&e.value.post("/uoms/"+e.value.id+"/update",{onSuccess:()=>{u("modalClose")},preserveState:!0,replace:!0})}return(p,s)=>(n(),C(w,{to:"body"},[a($,{open:i.showModal,onModalClose:s[3]||(s[3]=l=>p.$emit("modalClose"))},{header:r(()=>[o("div",N,[t.uom?(n(),d("span",E," Editing ")):V("",!0),t.uom?(n(),d("span",T,k(t.uom.name),1)):(n(),d("span",U," Create New UOM "))])]),default:r(()=>[o("form",{onSubmit:S(y,["prevent"]),id:"submit"},[o("div",j,[o("div",D,[a(g,{modelValue:e.value.name,"onUpdate:modelValue":s[0]||(s[0]=l=>e.value.name=l),error:e.value.errors.name,required:"true"},{default:r(()=>[f(" Name ")]),_:1},8,["modelValue","error"])]),o("div",F,[a(g,{modelValue:e.value.color,"onUpdate:modelValue":s[1]||(s[1]=l=>e.value.color=l),error:e.value.errors.color},{default:r(()=>[f(" Hex Color ")]),_:1},8,["modelValue","error"])])]),o("div",O,[o("div",q,[a(v,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:s[2]||(s[2]=l=>p.$emit("modalClose")),form:"submit"},{default:r(()=>[a(_(B),{class:"w-4 h-4"}),H]),_:1}),a(v,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[a(_(M),{class:"w-4 h-4"}),z]),_:1})])])],32)]),_:1},8,["open"])]))}};export{R as default};
