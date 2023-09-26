import{g as h,T as c,h as k,c as u,a as r,w as n,q as C,o as l,b as a,f as w,t as $,l as m,d as v,u as p,e as D}from"./app.024e39e5.js";import{_ as f}from"./Button.33536ddb.js";import{_}from"./DatePicker.53db14f6.js";import{_ as y}from"./FormInput.a4bc002f.js";import{_ as S}from"./Modal.e83c5b6a.js";import{r as B}from"./ArrowUturnLeftIcon.00eea8ad.js";import{r as M}from"./ArrowUturnDownIcon.2b6517a5.js";import{r as U}from"./CheckCircleIcon.71ce7f83.js";import"./main.c0edd862.js";import"./open-closed.4c597dc4.js";import"./platform.ff812502.js";const N={class:"flex flex-col md:flex-row space-x-2"},j=a("span",{class:"text-gray-600"}," Editing ",-1),F={key:0},T=["onSubmit"],E={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6 pb-2"},O={class:"sm:col-span-6"},q={class:"sm:col-span-6"},I={class:"sm:col-span-6"},A={class:"sm:col-span-6"},K={class:"sm:col-span-6"},P={class:"flex space-x-1 mt-5 justify-end"},z=a("span",null," Back ",-1),G=a("span",null," Unbind ",-1),H=a("span",null," Save ",-1),ae={__name:"Form",props:{vend:Object,countries:Object,permissions:[Array,Object],type:String,showModal:Boolean},emits:["modalClose"],setup(s,{emit:g}){const d=s,e=h(c(b()));k(()=>{e.value=d.vend?c(d.vend):c(b()),e.value.name=d.vend.customer_code?d.vend.customer_code+"    "+d.vend.customer_name:d.vend.name});function b(){return{name:"",begin_date:"",serial_num:"",termination_date:"",private_key:""}}function x(){e.value.clearErrors(),e.value.post("/vends/"+e.value.id+"/update",{onSuccess:()=>{g("modalClose")},preserveState:!0,replace:!0})}function V(i){e.value.post("/vends/"+i+"/unbind",{onSuccess:()=>{g("modalClose")},preserveState:!0,replace:!0})}return(i,t)=>(l(),u(C,{to:"body"},[r(S,{open:s.showModal,onModalClose:t[7]||(t[7]=o=>i.$emit("modalClose"))},{header:n(()=>[a("div",N,[j,s.vend?(l(),w("span",F,$(s.vend.code),1)):m("",!0)])]),default:n(()=>[a("form",{onSubmit:D(x,["prevent"]),id:"submit"},[a("div",E,[a("div",O,[r(y,{modelValue:e.value.name,"onUpdate:modelValue":t[0]||(t[0]=o=>e.value.name=o),error:e.value.errors.name,required:"true",disabled:s.vend.customer_code&&s.vend.customer_name},{default:n(()=>[v(" Name ")]),_:1},8,["modelValue","error","disabled"])]),a("div",q,[r(y,{modelValue:e.value.private_key,"onUpdate:modelValue":t[1]||(t[1]=o=>e.value.private_key=o),error:e.value.errors.private_key,disabled:!s.permissions.includes("update vends")},{default:n(()=>[v(" Private Key ")]),_:1},8,["modelValue","error","disabled"])]),a("div",I,[s.permissions.includes("update vends")?(l(),u(_,{key:0,modelValue:e.value.begin_date,"onUpdate:modelValue":t[2]||(t[2]=o=>e.value.begin_date=o),error:e.value.errors.begin_date,onInput:t[3]||(t[3]=o=>i.onDateFromChanged())},{default:n(()=>[v(" Begin Date (Default is the Creation/ First Invoice Date) ")]),_:1},8,["modelValue","error"])):m("",!0)]),a("div",A,[s.permissions.includes("update vends")?(l(),u(_,{key:0,modelValue:e.value.termination_date,"onUpdate:modelValue":t[4]||(t[4]=o=>e.value.termination_date=o),error:e.value.errors.termination_date,minDate:e.value.begin_date},{default:n(()=>[v(" Termination Date (Default is the Unbinding Date from CMS, status change) ")]),_:1},8,["modelValue","error","minDate"])):m("",!0)])]),a("div",K,[a("div",P,[r(f,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:t[5]||(t[5]=o=>i.$emit("modalClose")),form:"submit"},{default:n(()=>[r(p(B),{class:"w-4 h-4"}),z]),_:1}),s.vend.latestVendBinding&&s.vend.latestVendBinding.customer&&s.permissions.includes("update vends")?(l(),u(f,{key:0,type:"button",class:"bg-yellow-500 hover:bg-yellow-600 text-white flex space-x-1",onClick:t[6]||(t[6]=o=>V(e.value.id))},{default:n(()=>[r(p(M),{class:"w-4 h-4"}),G]),_:1})):m("",!0),s.permissions.includes("update vends")?(l(),u(f,{key:1,type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:n(()=>[r(p(U),{class:"w-4 h-4"}),H]),_:1})):m("",!0)])])],40,T)]),_:1},8,["open"])]))}};export{ae as default};
