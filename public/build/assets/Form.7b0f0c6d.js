import{i as g,u as c,j as V,o as u,c as b,a as t,w as a,T as x,d as s,g as i,p as C,t as w,b as y,e as S,f as d}from"./app.7b13628b.js";import{_ as f}from"./Button.d69eb374.js";import{_ as k,a as n,r as $}from"./Modal.c96b1c99.js";import{r as B}from"./ArrowUturnLeftIcon.0837d764.js";import"./open-closed.8ceffd08.js";const N={class:"flex flex-col md:flex-row space-x-2"},q={key:0,class:"text-gray-600"},M={key:1},U={key:2,class:"text-gray-600"},j=["onSubmit"],E={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},F={class:"sm:col-span-4"},T=d(" Name "),D={class:"sm:col-span-2"},O=d(" Code "),P={class:"sm:col-span-3"},z=d(" Currency "),A={class:"sm:col-span-3"},G=d(" Symbol "),H={class:"sm:col-span-3"},I=d(" Phone Code "),J={class:"sm:col-span-6"},K={class:"flex space-x-1 mt-5 justify-end"},L=s("span",null," Back ",-1),Q=s("span",null," Save ",-1),ee={__name:"Form",props:{country:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(m,{emit:_}){const l=m,e=g(c(p()));V(()=>{e.value=l.country?c(l.country):c(p())});function p(){return{name:"",code:"",currency_name:"",currency_symbol:"",phone_code:""}}function h(){e.value.clearErrors(),l.type==="create"&&e.value.post("/countries/create",{onSuccess:()=>{_("modalClose")},preserveState:!0,replace:!0}),l.type==="update"&&e.value.post("/countries/"+e.value.id+"/update",{onSuccess:()=>{_("modalClose")},preserveState:!0,replace:!0})}return(v,o)=>(u(),b(x,{to:"body"},[t(k,{open:m.showModal,onModalClose:o[6]||(o[6]=r=>v.$emit("modalClose"))},{header:a(()=>[s("div",N,[l.country?(u(),i("span",q," Editing ")):C("",!0),l.country?(u(),i("span",M,w(l.country.name),1)):(u(),i("span",U," Create New Country "))])]),default:a(()=>[s("form",{onSubmit:S(h,["prevent"]),id:"submit"},[s("div",E,[s("div",F,[t(n,{modelValue:e.value.name,"onUpdate:modelValue":o[0]||(o[0]=r=>e.value.name=r),error:e.value.errors.name,required:"true"},{default:a(()=>[T]),_:1},8,["modelValue","error"])]),s("div",D,[t(n,{modelValue:e.value.code,"onUpdate:modelValue":o[1]||(o[1]=r=>e.value.code=r),error:e.value.errors.code,required:"true"},{default:a(()=>[O]),_:1},8,["modelValue","error"])]),s("div",P,[t(n,{modelValue:e.value.currency_name,"onUpdate:modelValue":o[2]||(o[2]=r=>e.value.currency_name=r),error:e.value.errors.currency_name,required:"true"},{default:a(()=>[z]),_:1},8,["modelValue","error"])]),s("div",A,[t(n,{modelValue:e.value.currency_symbol,"onUpdate:modelValue":o[3]||(o[3]=r=>e.value.currency_symbol=r),error:e.value.errors.currency_symbol,required:"true"},{default:a(()=>[G]),_:1},8,["modelValue","error"])]),s("div",H,[t(n,{modelValue:e.value.phone_code,"onUpdate:modelValue":o[4]||(o[4]=r=>e.value.phone_code=r),error:e.value.errors.phone_code,required:"true"},{default:a(()=>[I]),_:1},8,["modelValue","error"])])]),s("div",J,[s("div",K,[t(f,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:o[5]||(o[5]=r=>v.$emit("modalClose")),form:"submit"},{default:a(()=>[t(y(B),{class:"w-4 h-4"}),L]),_:1}),t(f,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:a(()=>[t(y($),{class:"w-4 h-4"}),Q]),_:1})])])],40,j)]),_:1},8,["open"])]))}};export{ee as default};
