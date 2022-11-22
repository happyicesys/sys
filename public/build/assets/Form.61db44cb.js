import{i as h,u as d,j as y,o as n,c as b,a,w as r,T as C,d as o,g as m,p as w,t as V,b as _,e as S,f as g}from"./app.7b13628b.js";import{_ as f}from"./Button.d69eb374.js";import{_ as k,a as v,r as $}from"./Modal.c96b1c99.js";import{r as B}from"./ArrowUturnLeftIcon.0837d764.js";import"./open-closed.8ceffd08.js";const M={class:"flex flex-col md:flex-row space-x-2"},N={key:0,class:"text-gray-600"},j={key:1},E={key:2,class:"text-gray-600"},F=["onSubmit"],T={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},U={class:"sm:col-span-6"},D=g(" Name "),O={class:"sm:col-span-6"},q=g(" Hex Color "),H={class:"sm:col-span-6"},z={class:"flex space-x-1 mt-5 justify-end"},A=o("span",null," Back ",-1),G=o("span",null," Save ",-1),Q={__name:"Form",props:{uom:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(u,{emit:i}){const t=u,e=h(d(c()));y(()=>{e.value=t.uom?d(t.uom):d(c())});function c(){return{name:"",color:""}}function x(){e.value.clearErrors(),t.type==="create"&&e.value.post("/uoms/create",{onSuccess:()=>{i("modalClose")},preserveState:!0,replace:!0}),t.type==="update"&&e.value.post("/uoms/"+e.value.id+"/update",{onSuccess:()=>{i("modalClose")},preserveState:!0,replace:!0})}return(p,s)=>(n(),b(C,{to:"body"},[a(k,{open:u.showModal,onModalClose:s[3]||(s[3]=l=>p.$emit("modalClose"))},{header:r(()=>[o("div",M,[t.uom?(n(),m("span",N," Editing ")):w("",!0),t.uom?(n(),m("span",j,V(t.uom.name),1)):(n(),m("span",E," Create New UOM "))])]),default:r(()=>[o("form",{onSubmit:S(x,["prevent"]),id:"submit"},[o("div",T,[o("div",U,[a(v,{modelValue:e.value.name,"onUpdate:modelValue":s[0]||(s[0]=l=>e.value.name=l),error:e.value.errors.name,required:"true"},{default:r(()=>[D]),_:1},8,["modelValue","error"])]),o("div",O,[a(v,{modelValue:e.value.color,"onUpdate:modelValue":s[1]||(s[1]=l=>e.value.color=l),error:e.value.errors.color},{default:r(()=>[q]),_:1},8,["modelValue","error"])])]),o("div",H,[o("div",z,[a(f,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:s[2]||(s[2]=l=>p.$emit("modalClose")),form:"submit"},{default:r(()=>[a(_(B),{class:"w-4 h-4"}),A]),_:1}),a(f,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[a(_($),{class:"w-4 h-4"}),G]),_:1})])])],40,F)]),_:1},8,["open"])]))}};export{Q as default};
