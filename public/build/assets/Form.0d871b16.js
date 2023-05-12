import{h as y,T as d,j as x,c as h,a,w as r,s as b,o as n,b as o,f as c,m as C,t as w,d as f,u as _,e as V}from"./app.69f34740.js";import{_ as g}from"./Button.5adfb1f9.js";import{_ as S}from"./FormInput.5914a32e.js";import{_ as $}from"./FormTextarea.38d15442.js";import{_ as k}from"./Modal.2750dab9.js";import{r as B}from"./ArrowUturnLeftIcon.c99bd19e.js";import{r as G}from"./CheckCircleIcon.be3634df.js";import"./open-closed.1b2a7114.js";const N={class:"flex flex-col md:flex-row space-x-2"},M={key:0,class:"text-gray-600"},j={key:1},D={key:2,class:"text-gray-600"},E=["onSubmit"],T={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},F={class:"sm:col-span-6"},U={class:"sm:col-span-6"},q={class:"sm:col-span-6"},O={class:"flex space-x-1 mt-5 justify-end"},z=o("span",null," Back ",-1),A=o("span",null," Save ",-1),W={__name:"Form",props:{categoryGroup:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(i,{emit:m}){const t=i,e=y(d(u()));x(()=>{e.value=t.categoryGroup?d(t.categoryGroup):d(u())});function u(){return{name:"",desc:""}}function v(){e.value.clearErrors(),t.type==="create"&&e.value.post("/category-groups/create",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0}),t.type==="update"&&e.value.post("/category-groups/"+e.value.id+"/update",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0})}return(p,s)=>(n(),h(b,{to:"body"},[a(k,{open:i.showModal,onModalClose:s[3]||(s[3]=l=>p.$emit("modalClose"))},{header:r(()=>[o("div",N,[t.categoryGroup?(n(),c("span",M," Editing ")):C("",!0),t.category?(n(),c("span",j,w(t.categoryGroup.name),1)):(n(),c("span",D," Create New Category Group "))])]),default:r(()=>[o("form",{onSubmit:V(v,["prevent"]),id:"submit"},[o("div",T,[o("div",F,[a(S,{modelValue:e.value.name,"onUpdate:modelValue":s[0]||(s[0]=l=>e.value.name=l),error:e.value.errors.name,required:"true"},{default:r(()=>[f(" Name ")]),_:1},8,["modelValue","error"])]),o("div",U,[a($,{modelValue:e.value.desc,"onUpdate:modelValue":s[1]||(s[1]=l=>e.value.desc=l),error:e.value.errors.desc},{default:r(()=>[f(" Desc ")]),_:1},8,["modelValue","error"])])]),o("div",q,[o("div",O,[a(g,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:s[2]||(s[2]=l=>p.$emit("modalClose")),form:"submit"},{default:r(()=>[a(_(B),{class:"w-4 h-4"}),z]),_:1}),a(g,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[a(_(G),{class:"w-4 h-4"}),A]),_:1})])])],40,E)]),_:1},8,["open"])]))}};export{W as default};
