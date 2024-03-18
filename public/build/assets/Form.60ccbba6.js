import{g as x,T as d,h,c as b,a,w as r,p as C,o as n,b as o,f as c,l as w,t as V,d as f,u as _,e as $}from"./app.c8734f48.js";import{_ as g}from"./Button.4b8981fe.js";import{_ as k}from"./FormInput.06b6bdfe.js";import{_ as S}from"./FormTextarea.b72a51e8.js";import{_ as B}from"./Modal.ab16ba7c.js";import{r as G}from"./ArrowUturnLeftIcon.c6295de0.js";import{r as N}from"./CheckCircleIcon.d7bc9c9a.js";import"./keyboard.d1255e04.js";import"./disposables.1619ac17.js";const M={class:"flex flex-col md:flex-row space-x-2"},D={key:0,class:"text-gray-600"},E={key:1},T={key:2,class:"text-gray-600"},j={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},F={class:"sm:col-span-6"},U={class:"sm:col-span-6"},q={class:"sm:col-span-6"},O={class:"flex space-x-1 mt-5 justify-end"},z=o("span",null," Back ",-1),A=o("span",null," Save ",-1),X={__name:"Form",props:{categoryGroup:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(i,{emit:v}){const t=i,m=v,e=x(d(u()));h(()=>{e.value=t.categoryGroup?d(t.categoryGroup):d(u())});function u(){return{name:"",desc:""}}function y(){e.value.clearErrors(),t.type==="create"&&e.value.post("/category-groups/create",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0}),t.type==="update"&&e.value.post("/category-groups/"+e.value.id+"/update",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0})}return(p,s)=>(n(),b(C,{to:"body"},[a(B,{open:i.showModal,onModalClose:s[3]||(s[3]=l=>p.$emit("modalClose"))},{header:r(()=>[o("div",M,[t.categoryGroup?(n(),c("span",D," Editing ")):w("",!0),t.category?(n(),c("span",E,V(t.categoryGroup.name),1)):(n(),c("span",T," Create New Category Group "))])]),default:r(()=>[o("form",{onSubmit:$(y,["prevent"]),id:"submit"},[o("div",j,[o("div",F,[a(k,{modelValue:e.value.name,"onUpdate:modelValue":s[0]||(s[0]=l=>e.value.name=l),error:e.value.errors.name,required:"true"},{default:r(()=>[f(" Name ")]),_:1},8,["modelValue","error"])]),o("div",U,[a(S,{modelValue:e.value.desc,"onUpdate:modelValue":s[1]||(s[1]=l=>e.value.desc=l),error:e.value.errors.desc},{default:r(()=>[f(" Desc ")]),_:1},8,["modelValue","error"])])]),o("div",q,[o("div",O,[a(g,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:s[2]||(s[2]=l=>p.$emit("modalClose")),form:"submit"},{default:r(()=>[a(_(G),{class:"w-4 h-4"}),z]),_:1}),a(g,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[a(_(N),{class:"w-4 h-4"}),A]),_:1})])])],32)]),_:1},8,["open"])]))}};export{X as default};
