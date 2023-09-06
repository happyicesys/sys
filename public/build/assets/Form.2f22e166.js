import{g as f,T as n,h as C,c as k,a,w as l,q as w,o as i,b as o,f as c,l as v,t as x,d as g,u as b,e as $,R as B}from"./app.df51a4ec.js";import{_ as y}from"./Button.a46aca21.js";import{_ as h}from"./FormInput.5d6cb1d3.js";import{_ as M}from"./Modal.5942aa2b.js";import{r as N}from"./ArrowUturnLeftIcon.77a5395c.js";import{r as j}from"./CheckCircleIcon.ed2e6fa3.js";import"./open-closed.7817bdfe.js";const T={class:"flex flex-col md:flex-row space-x-2"},q={key:0,class:"text-gray-600"},E={key:1},O={key:2,class:"text-gray-600"},U=["onSubmit"],D={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},F={class:"sm:col-span-6"},P={class:"sm:col-span-6"},R=o("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Telco ",-1),z={key:0,class:"text-sm text-red-600"},A={class:"sm:col-span-6"},G={class:"sm:col-span-6"},H={class:"flex space-x-1 mt-5 justify-end"},I=o("span",null," Back ",-1),J=o("span",null," Save ",-1),ee={__name:"Form",props:{simcard:Object,telcos:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(m,{emit:u}){const t=m,e=f(n(_())),p=f([]);C(()=>{e.value=t.simcard?n(t.simcard):n(_()),p.value=t.telcos.data.map(d=>({id:d.id,name:d.name}))});function _(){return{code:"",phone_number:"",telco_id:""}}function S(){e.value.clearErrors(),t.type==="create"&&e.value.post("/simcards/create",{onSuccess:()=>{u("modalClose")},preserveState:!0,replace:!0}),t.type==="update"&&e.value.post("/simcards/"+e.value.id+"/update",{onSuccess:()=>{u("modalClose")},preserveState:!0,replace:!0})}return(d,s)=>{const V=B("MultiSelect");return i(),k(w,{to:"body"},[a(M,{open:m.showModal,onModalClose:s[4]||(s[4]=r=>d.$emit("modalClose"))},{header:l(()=>[o("div",T,[t.simcard?(i(),c("span",q," Editing ")):v("",!0),t.simcard?(i(),c("span",E,x(t.simcard.name),1)):(i(),c("span",O," Create New Simcard "))])]),default:l(()=>[o("form",{onSubmit:$(S,["prevent"]),id:"submit"},[o("div",D,[o("div",F,[a(h,{modelValue:e.value.code,"onUpdate:modelValue":s[0]||(s[0]=r=>e.value.code=r),error:e.value.errors.code,required:"true"},{default:l(()=>[g(" Simcard Number ")]),_:1},8,["modelValue","error"])]),o("div",P,[R,a(V,{modelValue:e.value.telco_id,"onUpdate:modelValue":s[1]||(s[1]=r=>e.value.telco_id=r),options:p.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),e.value.errors.telco_id?(i(),c("div",z,x(e.value.errors.telco_id),1)):v("",!0)]),o("div",A,[a(h,{modelValue:e.value.code,"onUpdate:modelValue":s[2]||(s[2]=r=>e.value.code=r),error:e.value.errors.code,required:"true"},{default:l(()=>[g(" Simcard Number ")]),_:1},8,["modelValue","error"])])]),o("div",G,[o("div",H,[a(y,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:s[3]||(s[3]=r=>d.$emit("modalClose")),form:"submit"},{default:l(()=>[a(b(N),{class:"w-4 h-4"}),I]),_:1}),a(y,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:l(()=>[a(b(j),{class:"w-4 h-4"}),J]),_:1})])])],40,U)]),_:1},8,["open"])])}}};export{ee as default};