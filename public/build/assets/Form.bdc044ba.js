import{g as x,T as c,h,c as y,a,w as r,p as b,o as l,b as t,f as i,l as w,t as C,d as k,u as f,e as S}from"./app.c8734f48.js";import{_}from"./Button.4b8981fe.js";import{_ as $}from"./FormInput.06b6bdfe.js";import{_ as V}from"./Modal.ab16ba7c.js";import{r as B}from"./ArrowUturnLeftIcon.c6295de0.js";import{r as N}from"./CheckCircleIcon.d7bc9c9a.js";import"./keyboard.d1255e04.js";import"./disposables.1619ac17.js";const M={class:"flex flex-col md:flex-row space-x-2"},T={key:0,class:"text-gray-600"},E={key:1},j={key:2,class:"text-gray-600"},D={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},F={class:"sm:col-span-6"},q={class:"sm:col-span-6"},O={class:"flex space-x-1 mt-5 justify-end"},U=t("span",null," Back ",-1),z=t("span",null," Save ",-1),Q={__name:"Form",props:{telco:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(m,{emit:g}){const s=m,d=g,e=x(c(p()));h(()=>{e.value=s.telco?c(s.telco):c(p())});function p(){return{name:""}}function v(){e.value.clearErrors(),s.type==="create"&&e.value.post("/telcos/create",{onSuccess:()=>{d("modalClose")},preserveState:!0,replace:!0}),s.type==="update"&&e.value.post("/telcos/"+e.value.id+"/update",{onSuccess:()=>{d("modalClose")},preserveState:!0,replace:!0})}return(u,o)=>(l(),y(b,{to:"body"},[a(V,{open:m.showModal,onModalClose:o[2]||(o[2]=n=>u.$emit("modalClose"))},{header:r(()=>[t("div",M,[s.telco?(l(),i("span",T," Editing ")):w("",!0),s.telco?(l(),i("span",E,C(s.telco.name),1)):(l(),i("span",j," Create New Telco "))])]),default:r(()=>[t("form",{onSubmit:S(v,["prevent"]),id:"submit"},[t("div",D,[t("div",F,[a($,{modelValue:e.value.name,"onUpdate:modelValue":o[0]||(o[0]=n=>e.value.name=n),error:e.value.errors.name,required:"true"},{default:r(()=>[k(" Name ")]),_:1},8,["modelValue","error"])])]),t("div",q,[t("div",O,[a(_,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:o[1]||(o[1]=n=>u.$emit("modalClose")),form:"submit"},{default:r(()=>[a(f(B),{class:"w-4 h-4"}),U]),_:1}),a(_,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[a(f(N),{class:"w-4 h-4"}),z]),_:1})])])],32)]),_:1},8,["open"])]))}};export{Q as default};
