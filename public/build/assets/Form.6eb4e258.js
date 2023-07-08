import{h as V,T as c,j as b,c as h,a,w as t,s as x,o as d,b as r,f as m,m as C,t as w,d as n,u as v,e as S}from"./app.1f3b9bf1.js";import{_ as y}from"./Button.52c6fc2d.js";import{_ as u}from"./FormInput.2df82814.js";import{_ as k,r as $}from"./Modal.c70f61b2.js";import{r as B}from"./ArrowUturnLeftIcon.812e4f4c.js";import"./open-closed.56f1f1ae.js";const N={class:"flex flex-col md:flex-row space-x-2"},q={key:0,class:"text-gray-600"},M={key:1},U={key:2,class:"text-gray-600"},j=["onSubmit"],E={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},T={class:"sm:col-span-4"},D={class:"sm:col-span-2"},F={class:"sm:col-span-3"},O={class:"sm:col-span-3"},P={class:"sm:col-span-3"},z={class:"sm:col-span-6"},A={class:"flex space-x-1 mt-5 justify-end"},G=r("span",null," Back ",-1),H=r("span",null," Save ",-1),W={__name:"Form",props:{country:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(i,{emit:p}){const l=i,e=V(c(_()));b(()=>{e.value=l.country?c(l.country):c(_())});function _(){return{name:"",code:"",currency_name:"",currency_symbol:"",phone_code:""}}function g(){e.value.clearErrors(),l.type==="create"&&e.value.post("/countries/create",{onSuccess:()=>{p("modalClose")},preserveState:!0,replace:!0}),l.type==="update"&&e.value.post("/countries/"+e.value.id+"/update",{onSuccess:()=>{p("modalClose")},preserveState:!0,replace:!0})}return(f,o)=>(d(),h(x,{to:"body"},[a(k,{open:i.showModal,onModalClose:o[6]||(o[6]=s=>f.$emit("modalClose"))},{header:t(()=>[r("div",N,[l.country?(d(),m("span",q," Editing ")):C("",!0),l.country?(d(),m("span",M,w(l.country.name),1)):(d(),m("span",U," Create New Country "))])]),default:t(()=>[r("form",{onSubmit:S(g,["prevent"]),id:"submit"},[r("div",E,[r("div",T,[a(u,{modelValue:e.value.name,"onUpdate:modelValue":o[0]||(o[0]=s=>e.value.name=s),error:e.value.errors.name,required:"true"},{default:t(()=>[n(" Name ")]),_:1},8,["modelValue","error"])]),r("div",D,[a(u,{modelValue:e.value.code,"onUpdate:modelValue":o[1]||(o[1]=s=>e.value.code=s),error:e.value.errors.code,required:"true"},{default:t(()=>[n(" Code ")]),_:1},8,["modelValue","error"])]),r("div",F,[a(u,{modelValue:e.value.currency_name,"onUpdate:modelValue":o[2]||(o[2]=s=>e.value.currency_name=s),error:e.value.errors.currency_name,required:"true"},{default:t(()=>[n(" Currency ")]),_:1},8,["modelValue","error"])]),r("div",O,[a(u,{modelValue:e.value.currency_symbol,"onUpdate:modelValue":o[3]||(o[3]=s=>e.value.currency_symbol=s),error:e.value.errors.currency_symbol,required:"true"},{default:t(()=>[n(" Symbol ")]),_:1},8,["modelValue","error"])]),r("div",P,[a(u,{modelValue:e.value.phone_code,"onUpdate:modelValue":o[4]||(o[4]=s=>e.value.phone_code=s),error:e.value.errors.phone_code,required:"true"},{default:t(()=>[n(" Phone Code ")]),_:1},8,["modelValue","error"])])]),r("div",z,[r("div",A,[a(y,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:o[5]||(o[5]=s=>f.$emit("modalClose")),form:"submit"},{default:t(()=>[a(v(B),{class:"w-4 h-4"}),G]),_:1}),a(y,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:t(()=>[a(v($),{class:"w-4 h-4"}),H]),_:1})])])],40,j)]),_:1},8,["open"])]))}};export{W as default};
