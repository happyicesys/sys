import{i as g,u as c,j as V,o as u,c as b,a as t,w as a,T as x,d as r,g as m,p as C,t as w,b as v,e as S,f as d}from"./app.39868e0e.js";import{_ as y}from"./Button.330b9451.js";import{_ as n}from"./FormInput.0d282dd3.js";import{_ as k}from"./Modal.87d1d465.js";import{r as $}from"./ArrowUturnLeftIcon.eb1cecbc.js";import{r as B}from"./CheckCircleIcon.f15779f3.js";import"./open-closed.067d63b7.js";const N={class:"flex flex-col md:flex-row space-x-2"},q={key:0,class:"text-gray-600"},M={key:1},U={key:2,class:"text-gray-600"},j=["onSubmit"],E={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},F={class:"sm:col-span-4"},T=d(" Name "),D={class:"sm:col-span-2"},O=d(" Code "),P={class:"sm:col-span-3"},z=d(" Currency "),A={class:"sm:col-span-3"},G=d(" Symbol "),H={class:"sm:col-span-3"},I=d(" Phone Code "),J={class:"sm:col-span-6"},K={class:"flex space-x-1 mt-5 justify-end"},L=r("span",null," Back ",-1),Q=r("span",null," Save ",-1),re={__name:"Form",props:{country:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(i,{emit:_}){const l=i,e=g(c(p()));V(()=>{e.value=l.country?c(l.country):c(p())});function p(){return{name:"",code:"",currency_name:"",currency_symbol:"",phone_code:""}}function h(){e.value.clearErrors(),l.type==="create"&&e.value.post("/countries/create",{onSuccess:()=>{_("modalClose")},preserveState:!0,replace:!0}),l.type==="update"&&e.value.post("/countries/"+e.value.id+"/update",{onSuccess:()=>{_("modalClose")},preserveState:!0,replace:!0})}return(f,o)=>(u(),b(x,{to:"body"},[t(k,{open:i.showModal,onModalClose:o[6]||(o[6]=s=>f.$emit("modalClose"))},{header:a(()=>[r("div",N,[l.country?(u(),m("span",q," Editing ")):C("",!0),l.country?(u(),m("span",M,w(l.country.name),1)):(u(),m("span",U," Create New Country "))])]),default:a(()=>[r("form",{onSubmit:S(h,["prevent"]),id:"submit"},[r("div",E,[r("div",F,[t(n,{modelValue:e.value.name,"onUpdate:modelValue":o[0]||(o[0]=s=>e.value.name=s),error:e.value.errors.name,required:"true"},{default:a(()=>[T]),_:1},8,["modelValue","error"])]),r("div",D,[t(n,{modelValue:e.value.code,"onUpdate:modelValue":o[1]||(o[1]=s=>e.value.code=s),error:e.value.errors.code,required:"true"},{default:a(()=>[O]),_:1},8,["modelValue","error"])]),r("div",P,[t(n,{modelValue:e.value.currency_name,"onUpdate:modelValue":o[2]||(o[2]=s=>e.value.currency_name=s),error:e.value.errors.currency_name,required:"true"},{default:a(()=>[z]),_:1},8,["modelValue","error"])]),r("div",A,[t(n,{modelValue:e.value.currency_symbol,"onUpdate:modelValue":o[3]||(o[3]=s=>e.value.currency_symbol=s),error:e.value.errors.currency_symbol,required:"true"},{default:a(()=>[G]),_:1},8,["modelValue","error"])]),r("div",H,[t(n,{modelValue:e.value.phone_code,"onUpdate:modelValue":o[4]||(o[4]=s=>e.value.phone_code=s),error:e.value.errors.phone_code,required:"true"},{default:a(()=>[I]),_:1},8,["modelValue","error"])])]),r("div",J,[r("div",K,[t(y,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:o[5]||(o[5]=s=>f.$emit("modalClose")),form:"submit"},{default:a(()=>[t(v($),{class:"w-4 h-4"}),L]),_:1}),t(y,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:a(()=>[t(v(B),{class:"w-4 h-4"}),Q]),_:1})])])],40,j)]),_:1},8,["open"])]))}};export{re as default};
