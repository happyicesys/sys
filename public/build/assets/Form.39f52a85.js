import{i as f,u as n,j as C,U as k,o as i,c as w,a,w as l,T as $,d as o,g as c,p as v,t as x,b as g,e as B,f as y}from"./app.39868e0e.js";import{_ as b}from"./Button.330b9451.js";import{_ as h}from"./FormInput.0d282dd3.js";import{_ as M}from"./Modal.87d1d465.js";import{r as N}from"./ArrowUturnLeftIcon.eb1cecbc.js";import{r as j}from"./CheckCircleIcon.f15779f3.js";import"./open-closed.067d63b7.js";const T={class:"flex flex-col md:flex-row space-x-2"},U={key:0,class:"text-gray-600"},E={key:1},F={key:2,class:"text-gray-600"},O=["onSubmit"],q={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},D={class:"sm:col-span-6"},P=y(" Simcard Number "),z={class:"sm:col-span-6"},A=o("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Telco ",-1),G={key:0,class:"text-sm text-red-600"},H={class:"sm:col-span-6"},I=y(" Simcard Number "),J={class:"sm:col-span-6"},K={class:"flex space-x-1 mt-5 justify-end"},L=o("span",null," Back ",-1),Q=o("span",null," Save ",-1),se={__name:"Form",props:{simcard:Object,telcos:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(m,{emit:u}){const t=m,e=f(n(_())),p=f([]);C(()=>{e.value=t.simcard?n(t.simcard):n(_()),p.value=t.telcos.data.map(d=>({id:d.id,name:d.name}))});function _(){return{code:"",phone_number:"",telco_id:""}}function S(){e.value.clearErrors(),t.type==="create"&&e.value.post("/simcards/create",{onSuccess:()=>{u("modalClose")},preserveState:!0,replace:!0}),t.type==="update"&&e.value.post("/simcards/"+e.value.id+"/update",{onSuccess:()=>{u("modalClose")},preserveState:!0,replace:!0})}return(d,s)=>{const V=k("MultiSelect");return i(),w($,{to:"body"},[a(M,{open:m.showModal,onModalClose:s[4]||(s[4]=r=>d.$emit("modalClose"))},{header:l(()=>[o("div",T,[t.simcard?(i(),c("span",U," Editing ")):v("",!0),t.simcard?(i(),c("span",E,x(t.simcard.name),1)):(i(),c("span",F," Create New Simcard "))])]),default:l(()=>[o("form",{onSubmit:B(S,["prevent"]),id:"submit"},[o("div",q,[o("div",D,[a(h,{modelValue:e.value.code,"onUpdate:modelValue":s[0]||(s[0]=r=>e.value.code=r),error:e.value.errors.code,required:"true"},{default:l(()=>[P]),_:1},8,["modelValue","error"])]),o("div",z,[A,a(V,{modelValue:e.value.telco_id,"onUpdate:modelValue":s[1]||(s[1]=r=>e.value.telco_id=r),options:p.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),e.value.errors.telco_id?(i(),c("div",G,x(e.value.errors.telco_id),1)):v("",!0)]),o("div",H,[a(h,{modelValue:e.value.code,"onUpdate:modelValue":s[2]||(s[2]=r=>e.value.code=r),error:e.value.errors.code,required:"true"},{default:l(()=>[I]),_:1},8,["modelValue","error"])])]),o("div",J,[o("div",K,[a(b,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:s[3]||(s[3]=r=>d.$emit("modalClose")),form:"submit"},{default:l(()=>[a(g(N),{class:"w-4 h-4"}),L]),_:1}),a(b,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:l(()=>[a(g(j),{class:"w-4 h-4"}),Q]),_:1})])])],40,O)]),_:1},8,["open"])])}}};export{se as default};
