import{i as x,u as i,j as y,o as n,c as b,a as t,w as r,T as w,d as s,g as d,p as C,t as V,b as _,e as S,f as g}from"./app.c318dc46.js";import{_ as f}from"./Button.27f88839.js";import{_ as k,a as v,r as $}from"./Modal.6db78482.js";import{r as B}from"./ArrowUturnLeftIcon.f4e69ee0.js";import"./open-closed.4eec9221.js";const N={class:"flex flex-col md:flex-row space-x-2"},M={key:0,class:"text-gray-600"},j={key:1},E={key:2,class:"text-gray-600"},F=["onSubmit"],T={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},U={class:"sm:col-span-6"},q=g(" Name "),D={class:"sm:col-span-6"},O=g(" Email "),z={class:"sm:col-span-6"},A={class:"flex space-x-1 mt-5 justify-end"},G=s("span",null," Back ",-1),H=s("span",null," Save ",-1),Q={__name:"Form",props:{user:Object,countries:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(u,{emit:m}){const a=u,e=x(i(c()));y(()=>{e.value=a.user?i(a.user):i(c())});function c(){return{name:"",email:""}}function h(){e.value.clearErrors(),a.type==="create"&&e.value.post("/users/create",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0}),a.type==="update"&&e.value.post("/users/"+e.value.id+"/update",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0})}return(p,o)=>(n(),b(w,{to:"body"},[t(k,{open:u.showModal,onModalClose:o[3]||(o[3]=l=>p.$emit("modalClose"))},{header:r(()=>[s("div",N,[a.user?(n(),d("span",M," Editing ")):C("",!0),a.user?(n(),d("span",j,V(a.user.name),1)):(n(),d("span",E," Create New User "))])]),default:r(()=>[s("form",{onSubmit:S(h,["prevent"]),id:"submit"},[s("div",T,[s("div",U,[t(v,{modelValue:e.value.name,"onUpdate:modelValue":o[0]||(o[0]=l=>e.value.name=l),error:e.value.errors.name,required:"true"},{default:r(()=>[q]),_:1},8,["modelValue","error"])]),s("div",D,[t(v,{modelValue:e.value.email,"onUpdate:modelValue":o[1]||(o[1]=l=>e.value.email=l),error:e.value.errors.email,required:"true"},{default:r(()=>[O]),_:1},8,["modelValue","error"])])]),s("div",z,[s("div",A,[t(f,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:o[2]||(o[2]=l=>p.$emit("modalClose")),form:"submit"},{default:r(()=>[t(_(B),{class:"w-4 h-4"}),G]),_:1}),t(f,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[t(_($),{class:"w-4 h-4"}),H]),_:1})])])],40,F)]),_:1},8,["open"])]))}};export{Q as default};
