import{g as b,T as u,h as x,c,a as r,w as t,p as y,o as p,b as s,d,u as f,l as h,e as V}from"./app.7b79dd1b.js";import{_ as v}from"./Button.aa5d1f67.js";import{_ as n}from"./FormInput.45917afd.js";import{_ as C}from"./Modal.fdabb716.js";import{r as k}from"./ArrowUturnLeftIcon.1e18a8c0.js";import{r as w}from"./CheckCircleIcon.8a0170dd.js";import"./open-closed.2818be23.js";import"./disposables.6d3c525e.js";const M=s("div",{class:"flex flex-col md:flex-row space-x-2"},[s("span",{class:"text-gray-600"}," Create New Machine ")],-1),$=["onSubmit"],B={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6 pb-2"},S={class:"sm:col-span-6"},N={class:"sm:col-span-6"},T={class:"sm:col-span-6"},U={class:"sm:col-span-6"},j={class:"flex space-x-1 mt-5 justify-end"},D=s("span",null," Back ",-1),q=s("span",null," Save ",-1),z={__name:"Create",props:{permissions:[Array,Object],type:String,showModal:Boolean},emits:["modalClose"],setup(l,{emit:_}){const e=b(u(i()));x(()=>{e.value=u(i())});function i(){return{begin_date:"",code:"",name:"",private_key:"",termination_date:""}}function g(){e.value.clearErrors(),e.value.post("/vends/create",{onSuccess:()=>{_("modalClose")},preserveState:!0,replace:!0})}return(m,o)=>(p(),c(y,{to:"body"},[r(C,{open:l.showModal,onModalClose:o[4]||(o[4]=a=>m.$emit("modalClose"))},{header:t(()=>[M]),default:t(()=>[s("form",{onSubmit:V(g,["prevent"]),id:"submit"},[s("div",B,[s("div",S,[r(n,{modelValue:e.value.code,"onUpdate:modelValue":o[0]||(o[0]=a=>e.value.code=a),error:e.value.errors.code,required:"true"},{default:t(()=>[d(" Machine ID ")]),_:1},8,["modelValue","error"])]),s("div",N,[r(n,{modelValue:e.value.name,"onUpdate:modelValue":o[1]||(o[1]=a=>e.value.name=a),error:e.value.errors.name},{default:t(()=>[d(" Name (Leave Blank if Bind from CMS) ")]),_:1},8,["modelValue","error"])]),s("div",T,[r(n,{modelValue:e.value.private_key,"onUpdate:modelValue":o[2]||(o[2]=a=>e.value.private_key=a),error:e.value.errors.private_key,disabled:!l.permissions.includes("update vends")},{default:t(()=>[d(" Private Key ")]),_:1},8,["modelValue","error","disabled"])])]),s("div",U,[s("div",j,[r(v,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:o[3]||(o[3]=a=>m.$emit("modalClose")),form:"submit"},{default:t(()=>[r(f(k),{class:"w-4 h-4"}),D]),_:1}),l.permissions.includes("update vends")?(p(),c(v,{key:0,type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:t(()=>[r(f(w),{class:"w-4 h-4"}),q]),_:1})):h("",!0)])])],40,$)]),_:1},8,["open"])]))}};export{z as default};