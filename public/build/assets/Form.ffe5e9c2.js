import{g as v,T as l,h as x,c as h,a as o,w as r,p as b,o as n,b as t,f as i,l as w,t as C,d as T,u as f,e as k}from"./app.242e5fba.js";import{_}from"./Button.be945d63.js";import{_ as S}from"./FormInput.15b0f65d.js";import{_ as $}from"./Modal.c8b43892.js";import{r as V}from"./ArrowUturnLeftIcon.88830442.js";import{r as B}from"./CheckCircleIcon.a28321ba.js";import"./keyboard.034c1cc1.js";import"./disposables.a3cad5e2.js";const N={class:"flex flex-col md:flex-row space-x-2"},M={key:0,class:"text-gray-600"},E={key:1},j={key:2,class:"text-gray-600"},D={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},F={class:"sm:col-span-6"},q={class:"sm:col-span-6"},O={class:"flex space-x-1 mt-5 justify-end"},P=t("span",null," Back ",-1),U=t("span",null," Save ",-1),Q={__name:"Form",props:{paymentTerm:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(d,{emit:y}){const s=d,p=y,e=v(l(c()));x(()=>{e.value=s.paymentTerm?l(s.paymentTerm):l(c())});function c(){return{name:""}}function g(){e.value.clearErrors(),s.type==="create"&&e.value.post("/payment-terms/create",{onSuccess:()=>{p("modalClose")},preserveState:!0,replace:!0}),s.type==="update"&&e.value.post("/payment-terms/"+e.value.id+"/update",{onSuccess:()=>{p("modalClose")},preserveState:!0,replace:!0})}return(u,a)=>(n(),h(b,{to:"body"},[o($,{open:d.showModal,onModalClose:a[2]||(a[2]=m=>u.$emit("modalClose"))},{header:r(()=>[t("div",N,[s.paymentTerm?(n(),i("span",M," Editing ")):w("",!0),s.paymentTerm?(n(),i("span",E,C(s.paymentTerm.name),1)):(n(),i("span",j," Create New Payment Term "))])]),default:r(()=>[t("form",{onSubmit:k(g,["prevent"]),id:"submit"},[t("div",D,[t("div",F,[o(S,{modelValue:e.value.name,"onUpdate:modelValue":a[0]||(a[0]=m=>e.value.name=m),error:e.value.errors.name,required:"true"},{default:r(()=>[T(" Name ")]),_:1},8,["modelValue","error"])])]),t("div",q,[t("div",O,[o(_,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:a[1]||(a[1]=m=>u.$emit("modalClose")),form:"submit"},{default:r(()=>[o(f(V),{class:"w-4 h-4"}),P]),_:1}),o(_,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[o(f(B),{class:"w-4 h-4"}),U]),_:1})])])],32)]),_:1},8,["open"])]))}};export{Q as default};
