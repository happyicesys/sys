import{g as h,T as m,h as y,c as b,a,w as r,p as C,o as n,b as o,f as d,l as w,t as V,d as f,u as _,e as S}from"./app.e38e8891.js";import{_ as v}from"./Button.46fd4c92.js";import{_ as g}from"./FormInput.e86626bc.js";import{_ as k}from"./Modal.8414f59c.js";import{r as $}from"./ArrowUturnLeftIcon.45d122e6.js";import{r as B}from"./CheckCircleIcon.2962e6ee.js";import"./open-closed.22813fed.js";import"./disposables.d3355ade.js";const M={class:"flex flex-col md:flex-row space-x-2"},N={key:0,class:"text-gray-600"},E={key:1},T={key:2,class:"text-gray-600"},U=["onSubmit"],j={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},D={class:"sm:col-span-6"},F={class:"sm:col-span-6"},O={class:"sm:col-span-6"},q={class:"flex space-x-1 mt-5 justify-end"},H=o("span",null," Back ",-1),z=o("span",null," Save ",-1),R={__name:"Form",props:{uom:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(u,{emit:i}){const t=u,e=h(m(c()));y(()=>{e.value=t.uom?m(t.uom):m(c())});function c(){return{name:"",color:""}}function x(){e.value.clearErrors(),t.type==="create"&&e.value.post("/uoms/create",{onSuccess:()=>{i("modalClose")},preserveState:!0,replace:!0}),t.type==="update"&&e.value.post("/uoms/"+e.value.id+"/update",{onSuccess:()=>{i("modalClose")},preserveState:!0,replace:!0})}return(p,s)=>(n(),b(C,{to:"body"},[a(k,{open:u.showModal,onModalClose:s[3]||(s[3]=l=>p.$emit("modalClose"))},{header:r(()=>[o("div",M,[t.uom?(n(),d("span",N," Editing ")):w("",!0),t.uom?(n(),d("span",E,V(t.uom.name),1)):(n(),d("span",T," Create New UOM "))])]),default:r(()=>[o("form",{onSubmit:S(x,["prevent"]),id:"submit"},[o("div",j,[o("div",D,[a(g,{modelValue:e.value.name,"onUpdate:modelValue":s[0]||(s[0]=l=>e.value.name=l),error:e.value.errors.name,required:"true"},{default:r(()=>[f(" Name ")]),_:1},8,["modelValue","error"])]),o("div",F,[a(g,{modelValue:e.value.color,"onUpdate:modelValue":s[1]||(s[1]=l=>e.value.color=l),error:e.value.errors.color},{default:r(()=>[f(" Hex Color ")]),_:1},8,["modelValue","error"])])]),o("div",O,[o("div",q,[a(v,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:s[2]||(s[2]=l=>p.$emit("modalClose")),form:"submit"},{default:r(()=>[a(_($),{class:"w-4 h-4"}),H]),_:1}),a(v,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[a(_(B),{class:"w-4 h-4"}),z]),_:1})])])],40,U)]),_:1},8,["open"])]))}};export{R as default};