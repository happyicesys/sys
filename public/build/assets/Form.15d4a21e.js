import{g as b,T as m,h,c as x,a as t,w as a,p as C,o as d,b as r,f as c,l as w,t as S,d as n,u as v,e as k}from"./app.c8734f48.js";import{_ as y}from"./Button.4b8981fe.js";import{_ as u}from"./FormInput.06b6bdfe.js";import{_ as $}from"./Modal.ab16ba7c.js";import{r as B}from"./ArrowUturnLeftIcon.c6295de0.js";import{r as N}from"./CheckCircleIcon.d7bc9c9a.js";import"./keyboard.d1255e04.js";import"./disposables.1619ac17.js";const q={class:"flex flex-col md:flex-row space-x-2"},M={key:0,class:"text-gray-600"},U={key:1},E={key:2,class:"text-gray-600"},T={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},j={class:"sm:col-span-4"},D={class:"sm:col-span-2"},F={class:"sm:col-span-3"},O={class:"sm:col-span-3"},P={class:"sm:col-span-3"},z={class:"sm:col-span-6"},A={class:"flex space-x-1 mt-5 justify-end"},G=r("span",null," Back ",-1),H=r("span",null," Save ",-1),Y={__name:"Form",props:{country:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(i,{emit:g}){const l=i,p=g,e=b(m(_()));h(()=>{e.value=l.country?m(l.country):m(_())});function _(){return{name:"",code:"",currency_name:"",currency_symbol:"",phone_code:""}}function V(){e.value.clearErrors(),l.type==="create"&&e.value.post("/countries/create",{onSuccess:()=>{p("modalClose")},preserveState:!0,replace:!0}),l.type==="update"&&e.value.post("/countries/"+e.value.id+"/update",{onSuccess:()=>{p("modalClose")},preserveState:!0,replace:!0})}return(f,o)=>(d(),x(C,{to:"body"},[t($,{open:i.showModal,onModalClose:o[6]||(o[6]=s=>f.$emit("modalClose"))},{header:a(()=>[r("div",q,[l.country?(d(),c("span",M," Editing ")):w("",!0),l.country?(d(),c("span",U,S(l.country.name),1)):(d(),c("span",E," Create New Country "))])]),default:a(()=>[r("form",{onSubmit:k(V,["prevent"]),id:"submit"},[r("div",T,[r("div",j,[t(u,{modelValue:e.value.name,"onUpdate:modelValue":o[0]||(o[0]=s=>e.value.name=s),error:e.value.errors.name,required:"true"},{default:a(()=>[n(" Name ")]),_:1},8,["modelValue","error"])]),r("div",D,[t(u,{modelValue:e.value.code,"onUpdate:modelValue":o[1]||(o[1]=s=>e.value.code=s),error:e.value.errors.code,required:"true"},{default:a(()=>[n(" Code ")]),_:1},8,["modelValue","error"])]),r("div",F,[t(u,{modelValue:e.value.currency_name,"onUpdate:modelValue":o[2]||(o[2]=s=>e.value.currency_name=s),error:e.value.errors.currency_name,required:"true"},{default:a(()=>[n(" Currency ")]),_:1},8,["modelValue","error"])]),r("div",O,[t(u,{modelValue:e.value.currency_symbol,"onUpdate:modelValue":o[3]||(o[3]=s=>e.value.currency_symbol=s),error:e.value.errors.currency_symbol,required:"true"},{default:a(()=>[n(" Symbol ")]),_:1},8,["modelValue","error"])]),r("div",P,[t(u,{modelValue:e.value.phone_code,"onUpdate:modelValue":o[4]||(o[4]=s=>e.value.phone_code=s),error:e.value.errors.phone_code,required:"true"},{default:a(()=>[n(" Phone Code ")]),_:1},8,["modelValue","error"])])]),r("div",z,[r("div",A,[t(y,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:o[5]||(o[5]=s=>f.$emit("modalClose")),form:"submit"},{default:a(()=>[t(v(B),{class:"w-4 h-4"}),G]),_:1}),t(y,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:a(()=>[t(v(N),{class:"w-4 h-4"}),H]),_:1})])])],32)]),_:1},8,["open"])]))}};export{Y as default};
