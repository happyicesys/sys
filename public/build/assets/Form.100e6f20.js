import{g,T as m,h as v,c as h,a as o,w as r,q as x,o as n,b as t,f as i,l as b,t as w,d as C,u as f,e as T}from"./app.024e39e5.js";import{_}from"./Button.33536ddb.js";import{_ as S}from"./FormInput.a4bc002f.js";import{_ as k}from"./Modal.e83c5b6a.js";import{r as $}from"./ArrowUturnLeftIcon.00eea8ad.js";import{r as V}from"./CheckCircleIcon.71ce7f83.js";import"./open-closed.4c597dc4.js";import"./platform.ff812502.js";const B={class:"flex flex-col md:flex-row space-x-2"},N={key:0,class:"text-gray-600"},M={key:1},E={key:2,class:"text-gray-600"},j=["onSubmit"],q={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},D={class:"sm:col-span-6"},F={class:"sm:col-span-6"},O={class:"flex space-x-1 mt-5 justify-end"},P=t("span",null," Back ",-1),U=t("span",null," Save ",-1),Q={__name:"Form",props:{paymentTerm:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(d,{emit:p}){const s=d,e=g(m(c()));v(()=>{e.value=s.paymentTerm?m(s.paymentTerm):m(c())});function c(){return{name:""}}function y(){e.value.clearErrors(),s.type==="create"&&e.value.post("/payment-terms/create",{onSuccess:()=>{p("modalClose")},preserveState:!0,replace:!0}),s.type==="update"&&e.value.post("/payment-terms/"+e.value.id+"/update",{onSuccess:()=>{p("modalClose")},preserveState:!0,replace:!0})}return(u,a)=>(n(),h(x,{to:"body"},[o(k,{open:d.showModal,onModalClose:a[2]||(a[2]=l=>u.$emit("modalClose"))},{header:r(()=>[t("div",B,[s.paymentTerm?(n(),i("span",N," Editing ")):b("",!0),s.paymentTerm?(n(),i("span",M,w(s.paymentTerm.name),1)):(n(),i("span",E," Create New Payment Term "))])]),default:r(()=>[t("form",{onSubmit:T(y,["prevent"]),id:"submit"},[t("div",q,[t("div",D,[o(S,{modelValue:e.value.name,"onUpdate:modelValue":a[0]||(a[0]=l=>e.value.name=l),error:e.value.errors.name,required:"true"},{default:r(()=>[C(" Name ")]),_:1},8,["modelValue","error"])])]),t("div",F,[t("div",O,[o(_,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:a[1]||(a[1]=l=>u.$emit("modalClose")),form:"submit"},{default:r(()=>[o(f($),{class:"w-4 h-4"}),P]),_:1}),o(_,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[o(f(V),{class:"w-4 h-4"}),U]),_:1})])])],40,j)]),_:1},8,["open"])]))}};export{Q as default};
