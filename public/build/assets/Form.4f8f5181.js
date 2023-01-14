import{i as y,u as d,j as h,o as n,c as x,a,w as r,T as b,d as o,g as c,p as C,t as w,b as _,e as V,f as g}from"./app.ad0996d0.js";import{_ as f}from"./Button.1d76c393.js";import{_ as S}from"./FormInput.89a1716a.js";import{_ as $}from"./FormTextarea.91c284da.js";import{_ as k}from"./Modal.333b53d2.js";import{r as B}from"./ArrowUturnLeftIcon.92b36951.js";import{r as G}from"./CheckCircleIcon.9ddca09e.js";import"./open-closed.4c312123.js";const N={class:"flex flex-col md:flex-row space-x-2"},M={key:0,class:"text-gray-600"},j={key:1},D={key:2,class:"text-gray-600"},E=["onSubmit"],F={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},T={class:"sm:col-span-6"},U=g(" Name "),q={class:"sm:col-span-6"},O=g(" Desc "),z={class:"sm:col-span-6"},A={class:"flex space-x-1 mt-5 justify-end"},H=o("span",null," Back ",-1),I=o("span",null," Save ",-1),Y={__name:"Form",props:{categoryGroup:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(i,{emit:u}){const t=i,e=y(d(m()));h(()=>{e.value=t.categoryGroup?d(t.categoryGroup):d(m())});function m(){return{name:"",desc:""}}function v(){e.value.clearErrors(),t.type==="create"&&e.value.post("/category-groups/create",{onSuccess:()=>{u("modalClose")},preserveState:!0,replace:!0}),t.type==="update"&&e.value.post("/category-groups/"+e.value.id+"/update",{onSuccess:()=>{u("modalClose")},preserveState:!0,replace:!0})}return(p,s)=>(n(),x(b,{to:"body"},[a(k,{open:i.showModal,onModalClose:s[3]||(s[3]=l=>p.$emit("modalClose"))},{header:r(()=>[o("div",N,[t.categoryGroup?(n(),c("span",M," Editing ")):C("",!0),t.category?(n(),c("span",j,w(t.categoryGroup.name),1)):(n(),c("span",D," Create New Category Group "))])]),default:r(()=>[o("form",{onSubmit:V(v,["prevent"]),id:"submit"},[o("div",F,[o("div",T,[a(S,{modelValue:e.value.name,"onUpdate:modelValue":s[0]||(s[0]=l=>e.value.name=l),error:e.value.errors.name,required:"true"},{default:r(()=>[U]),_:1},8,["modelValue","error"])]),o("div",q,[a($,{modelValue:e.value.desc,"onUpdate:modelValue":s[1]||(s[1]=l=>e.value.desc=l),error:e.value.errors.desc},{default:r(()=>[O]),_:1},8,["modelValue","error"])])]),o("div",z,[o("div",A,[a(f,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:s[2]||(s[2]=l=>p.$emit("modalClose")),form:"submit"},{default:r(()=>[a(_(B),{class:"w-4 h-4"}),H]),_:1}),a(f,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[a(_(G),{class:"w-4 h-4"}),I]),_:1})])])],40,E)]),_:1},8,["open"])]))}};export{Y as default};
