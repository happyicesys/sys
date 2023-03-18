import{i as h,u as d,j as y,o as n,c as b,a as o,w as r,T as w,d as t,g as i,p as C,t as V,b as _,e as S,f as v}from"./app.146697fc.js";import{_ as f}from"./Button.f622c4c1.js";import{_ as x}from"./FormInput.4d639907.js";import{_ as k}from"./Modal.3c9c22b5.js";import{r as $}from"./ArrowUturnLeftIcon.db134604.js";import{r as B}from"./CheckCircleIcon.d84c6873.js";import"./open-closed.00420709.js";const N={class:"flex flex-col md:flex-row space-x-2"},M={key:0,class:"text-gray-600"},T={key:1},j={key:2,class:"text-gray-600"},E=["onSubmit"],F={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},q={class:"sm:col-span-6"},D=v(" Name "),U={class:"sm:col-span-6"},O=v(" Rate "),R={class:"sm:col-span-6"},z={class:"flex space-x-1 mt-5 justify-end"},A=t("span",null," Back ",-1),G=t("span",null," Save ",-1),W={__name:"Form",props:{tax:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(m,{emit:u}){const a=m,e=h(d(c()));y(()=>{e.value=a.tax?d(a.tax):d(c())});function c(){return{name:"",rate:""}}function g(){e.value.clearErrors(),a.type==="create"&&e.value.post("/taxes/create",{onSuccess:()=>{u("modalClose")},preserveState:!0,replace:!0}),a.type==="update"&&e.value.post("/taxes/"+e.value.id+"/update",{onSuccess:()=>{u("modalClose")},preserveState:!0,replace:!0})}return(p,s)=>(n(),b(w,{to:"body"},[o(k,{open:m.showModal,onModalClose:s[3]||(s[3]=l=>p.$emit("modalClose"))},{header:r(()=>[t("div",N,[a.tax?(n(),i("span",M," Editing ")):C("",!0),a.tax?(n(),i("span",T,V(a.tax.name),1)):(n(),i("span",j," Create New Tax "))])]),default:r(()=>[t("form",{onSubmit:S(g,["prevent"]),id:"submit"},[t("div",F,[t("div",q,[o(x,{modelValue:e.value.name,"onUpdate:modelValue":s[0]||(s[0]=l=>e.value.name=l),error:e.value.errors.name,required:"true"},{default:r(()=>[D]),_:1},8,["modelValue","error"])]),t("div",U,[o(x,{modelValue:e.value.rate,"onUpdate:modelValue":s[1]||(s[1]=l=>e.value.rate=l),error:e.value.errors.rate,required:"true"},{default:r(()=>[O]),_:1},8,["modelValue","error"])])]),t("div",R,[t("div",z,[o(f,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:s[2]||(s[2]=l=>p.$emit("modalClose")),form:"submit"},{default:r(()=>[o(_($),{class:"w-4 h-4"}),A]),_:1}),o(f,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[o(_(B),{class:"w-4 h-4"}),G]),_:1})])])],40,E)]),_:1},8,["open"])]))}};export{W as default};
