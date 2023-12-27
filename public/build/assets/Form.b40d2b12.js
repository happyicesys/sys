import{g as h,T as d,h as y,c as b,a as o,w as r,p as w,o as n,b as t,f as i,l as C,t as V,d as f,u as _,e as S}from"./app.d127c760.js";import{_ as x}from"./Button.dc8b7417.js";import{_ as v}from"./FormInput.19ce6ee9.js";import{_ as k}from"./Modal.fb950373.js";import{r as $}from"./ArrowUturnLeftIcon.5583b0e0.js";import{r as B}from"./CheckCircleIcon.cbb5b174.js";import"./open-closed.b24bbe75.js";import"./disposables.a27cf54e.js";const N={class:"flex flex-col md:flex-row space-x-2"},M={key:0,class:"text-gray-600"},T={key:1},E={key:2,class:"text-gray-600"},j=["onSubmit"],q={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},D={class:"sm:col-span-6"},F={class:"sm:col-span-6"},U={class:"sm:col-span-6"},O={class:"flex space-x-1 mt-5 justify-end"},R=t("span",null," Back ",-1),z=t("span",null," Save ",-1),Q={__name:"Form",props:{tax:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(m,{emit:u}){const s=m,e=h(d(c()));y(()=>{e.value=s.tax?d(s.tax):d(c())});function c(){return{name:"",rate:""}}function g(){e.value.clearErrors(),s.type==="create"&&e.value.post("/taxes/create",{onSuccess:()=>{u("modalClose")},preserveState:!0,replace:!0}),s.type==="update"&&e.value.post("/taxes/"+e.value.id+"/update",{onSuccess:()=>{u("modalClose")},preserveState:!0,replace:!0})}return(p,a)=>(n(),b(w,{to:"body"},[o(k,{open:m.showModal,onModalClose:a[3]||(a[3]=l=>p.$emit("modalClose"))},{header:r(()=>[t("div",N,[s.tax?(n(),i("span",M," Editing ")):C("",!0),s.tax?(n(),i("span",T,V(s.tax.name),1)):(n(),i("span",E," Create New Tax "))])]),default:r(()=>[t("form",{onSubmit:S(g,["prevent"]),id:"submit"},[t("div",q,[t("div",D,[o(v,{modelValue:e.value.name,"onUpdate:modelValue":a[0]||(a[0]=l=>e.value.name=l),error:e.value.errors.name,required:"true"},{default:r(()=>[f(" Name ")]),_:1},8,["modelValue","error"])]),t("div",F,[o(v,{modelValue:e.value.rate,"onUpdate:modelValue":a[1]||(a[1]=l=>e.value.rate=l),error:e.value.errors.rate,required:"true"},{default:r(()=>[f(" Rate ")]),_:1},8,["modelValue","error"])])]),t("div",U,[t("div",O,[o(x,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:a[2]||(a[2]=l=>p.$emit("modalClose")),form:"submit"},{default:r(()=>[o(_($),{class:"w-4 h-4"}),R]),_:1}),o(x,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[o(_(B),{class:"w-4 h-4"}),z]),_:1})])])],40,j)]),_:1},8,["open"])]))}};export{Q as default};
