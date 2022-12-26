import{o as i,g as b,d as s,i as w,u as m,j as y,c as p,a as o,w as n,T as B,t as C,p as g,b as c,e as S,f as h}from"./app.0116af8b.js";import{_ as v}from"./Button.93626b7e.js";import{_}from"./FormInput.12eba5e6.js";import{_ as $}from"./Modal.1a9839a6.js";import{r as k}from"./ArrowUturnLeftIcon.9302ba00.js";import{r as M}from"./CheckCircleIcon.e7da16b6.js";import"./open-closed.1411b711.js";function N(a,u){return i(),b("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[s("path",{"fill-rule":"evenodd",d:"M2.232 12.207a.75.75 0 011.06.025l3.958 4.146V6.375a5.375 5.375 0 0110.75 0V9.25a.75.75 0 01-1.5 0V6.375a3.875 3.875 0 00-7.75 0v10.003l3.957-4.146a.75.75 0 011.085 1.036l-5.25 5.5a.75.75 0 01-1.085 0l-5.25-5.5a.75.75 0 01.025-1.06z","clip-rule":"evenodd"})])}const j={class:"flex flex-col md:flex-row space-x-2"},E=s("span",{class:"text-gray-600"}," Editing ",-1),F={key:0},T=["onSubmit"],U={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6 pb-2"},D={class:"sm:col-span-6"},O=h(" Name "),q={class:"sm:col-span-6"},z=h(" Serial Number "),A={class:"sm:col-span-6"},G={class:"flex space-x-1 mt-5 justify-end"},H=s("span",null," Back ",-1),I=s("span",null," Unbind ",-1),J=s("span",null," Save ",-1),Y={__name:"Form",props:{vend:Object,countries:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(a,{emit:u}){const l=a,e=w(m(f()));y(()=>{e.value=l.vend?m(l.vend):m(f()),e.value.name=l.vend.latestVendBinding?l.vend.latestVendBinding.customer.code+"    "+l.vend.latestVendBinding.customer.name:l.vend.name});function f(){return{name:"",serial_num:""}}function x(){e.value.clearErrors(),e.value.post("/vends/"+e.value.id+"/update",{onSuccess:()=>{u("modalClose")},preserveState:!0,replace:!0})}function V(d){e.value.post("/vends/"+d+"/unbind",{onSuccess:()=>{u("modalClose")},preserveState:!0,replace:!0})}return(d,t)=>(i(),p(B,{to:"body"},[o($,{open:a.showModal,onModalClose:t[4]||(t[4]=r=>d.$emit("modalClose"))},{header:n(()=>[s("div",j,[E,a.vend?(i(),b("span",F,C(a.vend.code),1)):g("",!0)])]),default:n(()=>[s("form",{onSubmit:S(x,["prevent"]),id:"submit"},[s("div",U,[s("div",D,[o(_,{modelValue:e.value.name,"onUpdate:modelValue":t[0]||(t[0]=r=>e.value.name=r),error:e.value.errors.name,required:"true",disabled:a.vend.latestVendBinding&&a.vend.latestVendBinding.customer},{default:n(()=>[O]),_:1},8,["modelValue","error","disabled"])]),s("div",q,[o(_,{modelValue:e.value.serial_num,"onUpdate:modelValue":t[1]||(t[1]=r=>e.value.serial_num=r),error:e.value.errors.serial_num},{default:n(()=>[z]),_:1},8,["modelValue","error"])])]),s("div",A,[s("div",G,[o(v,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:t[2]||(t[2]=r=>d.$emit("modalClose")),form:"submit"},{default:n(()=>[o(c(k),{class:"w-4 h-4"}),H]),_:1}),a.vend.latestVendBinding&&a.vend.latestVendBinding.customer?(i(),p(v,{key:0,type:"button",class:"bg-yellow-500 hover:bg-yellow-600 text-white flex space-x-1",onClick:t[3]||(t[3]=r=>V(e.value.id))},{default:n(()=>[o(c(N),{class:"w-4 h-4"}),I]),_:1})):g("",!0),o(v,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:n(()=>[o(c(M),{class:"w-4 h-4"}),J]),_:1})])])],40,T)]),_:1},8,["open"])]))}};export{Y as default};
