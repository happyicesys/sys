import{g as h,T as i,h as x,c as w,a as s,w as r,q as b,o as n,b as o,f as d,l as C,t as T,d as f,u as _,e as V}from"./app.7d502d37.js";import{_ as g}from"./Button.299af46d.js";import{_ as v}from"./FormInput.811513d1.js";import{_ as S,r as k}from"./Modal.87e37d6a.js";import{r as $}from"./ArrowUturnLeftIcon.5dee7dc0.js";import"./open-closed.7bf95799.js";const B={class:"flex flex-col md:flex-row space-x-2"},N={key:0,class:"text-gray-600"},M={key:1},E={key:2,class:"text-gray-600"},j=["onSubmit"],q={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},D={class:"sm:col-span-6"},F={class:"sm:col-span-6"},U={class:"sm:col-span-6"},L={class:"flex space-x-1 mt-5 justify-end"},O=o("span",null," Back ",-1),W=o("span",null," Save ",-1),K={__name:"Form",props:{locationType:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(c,{emit:m}){const a=c,e=h(i(p()));x(()=>{e.value=a.locationType?i(a.locationType):i(p())});function p(){return{name:"",weightage:""}}function y(){e.value.clearErrors(),a.type==="create"&&e.value.post("/location-types/create",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0}),a.type==="update"&&e.value.post("/location-types/"+e.value.id+"/update",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0})}return(u,t)=>(n(),w(b,{to:"body"},[s(S,{open:c.showModal,onModalClose:t[3]||(t[3]=l=>u.$emit("modalClose"))},{header:r(()=>[o("div",B,[a.locationType?(n(),d("span",N," Editing ")):C("",!0),a.locationType?(n(),d("span",M,T(a.locationType.name),1)):(n(),d("span",E," Create New Location Type "))])]),default:r(()=>[o("form",{onSubmit:V(y,["prevent"]),id:"submit"},[o("div",q,[o("div",D,[s(v,{modelValue:e.value.name,"onUpdate:modelValue":t[0]||(t[0]=l=>e.value.name=l),error:e.value.errors.name,required:"true"},{default:r(()=>[f(" Name ")]),_:1},8,["modelValue","error"])]),o("div",F,[s(v,{modelValue:e.value.weightage,"onUpdate:modelValue":t[1]||(t[1]=l=>e.value.weightage=l),error:e.value.errors.weightage},{default:r(()=>[f(" Weightage ")]),_:1},8,["modelValue","error"])])]),o("div",U,[o("div",L,[s(g,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:t[2]||(t[2]=l=>u.$emit("modalClose")),form:"submit"},{default:r(()=>[s(_($),{class:"w-4 h-4"}),O]),_:1}),s(g,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[s(_(k),{class:"w-4 h-4"}),W]),_:1})])])],40,j)]),_:1},8,["open"])]))}};export{K as default};
