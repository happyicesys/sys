import{g as v,T as y,K as h,h as D,f as _,a as d,u as r,w as n,F as $,o as m,Z as B,b as t,d as u,t as f,l as c,e as N,c as g}from"./app.2df6fe58.js";import{_ as U}from"./Authenticated.d1b73c72.js";import{_ as x}from"./Button.47a00e17.js";import{_ as w}from"./DatePicker.f4cc8693.js";import{_ as V}from"./FormInput.48525f12.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.c208199c.js";import{r as M}from"./ArrowUturnLeftIcon.05ce2cd6.js";import{r as F}from"./ArrowUturnDownIcon.1f4f59a9.js";import{r as O}from"./CheckCircleIcon.0d90e487.js";import"./open-closed.5f597199.js";import"./use-resolve-button-type.5431d81c.js";import"./RectangleStackIcon.2fa47d4d.js";import"./main.08a75441.js";const S={class:"font-semibold text-xl text-gray-800 leading-tight"},j={key:0},E={key:1},T=t("br",null,null,-1),H={key:2},I=t("br",null,null,-1),K={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},q={class:"mt-6 flex flex-col"},A={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},P={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll p-5"},R={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6 pb-2"},Z={class:"sm:col-span-6"},z={class:"sm:col-span-6"},G={class:"sm:col-span-6"},J={class:"sm:col-span-6"},L={class:"sm:col-span-6"},Q={class:"sm:col-span-6"},W={class:"flex space-x-1 mt-5 justify-end"},X=t("span",null," Back ",-1),Y=t("span",null," Unbind ",-1),ee=t("span",null," Save ",-1),pe={__name:"Edit",props:{operatorOptions:Object,vend:Object,type:String},setup(s){const l=s;v([]);const e=v(y(k()));v(!1),v([]);const C=v([]),b=v("");h().props.auth.operatorCountry,h().props.auth.operatorRole;const p=h().props.auth.permissions;v(moment().format("HH:mm:ss")),D(()=>{console.log(l.vend),l.type=="create"?b.value="Create New":b.value="Edit",C.value=[{id:"all",full_name:"All"},...l.operatorOptions.data.map(i=>({id:i.id,full_name:i.full_name}))],e.value=l.vend?y(l.vend):y(k()),e.value.name=l.vend.customer_code?l.vend.customer_code+"    "+l.vend.customer_name:l.vend.name});function k(){return{name:"",begin_date:"",serial_num:"",termination_date:"",private_key:""}}return(i,a)=>(m(),_($,null,[d(r(B),{title:"VM Management"}),d(U,null,{header:n(()=>[t("h2",S,[u(f(b.value)+" Vending Machine ",1),s.type=="update"?(m(),_("span",j,f(s.vend.code),1)):c("",!0),s.vend.customer_name?(m(),_("span",E,[T,u(" "+f(s.vend.customer_code)+" - "+f(s.vend.customer_name),1)])):!s.vend.customer_name&&s.vend.name?(m(),_("span",H,[I,u(" "+f(s.vend.name),1)])):c("",!0)])]),default:n(()=>[t("div",K,[t("div",q,[t("div",A,[t("div",P,[t("form",{onSubmit:a[8]||(a[8]=N((...o)=>i.submit&&i.submit(...o),["prevent"])),id:"submit"},[t("div",R,[t("div",Z,[d(V,{modelValue:e.value.code,"onUpdate:modelValue":a[0]||(a[0]=o=>e.value.code=o),error:e.value.errors.code,required:"true",disabled:s.vend.code},{default:n(()=>[u(" Code ")]),_:1},8,["modelValue","error","disabled"])]),t("div",z,[d(V,{modelValue:e.value.name,"onUpdate:modelValue":a[1]||(a[1]=o=>e.value.name=o),error:e.value.errors.name,disabled:s.vend.customer_code&&s.vend.customer_name},{default:n(()=>[u(" Name ")]),_:1},8,["modelValue","error","disabled"])]),t("div",G,[r(p).includes("update vends")?(m(),g(w,{key:0,modelValue:e.value.begin_date,"onUpdate:modelValue":a[2]||(a[2]=o=>e.value.begin_date=o),error:e.value.errors.begin_date,onInput:a[3]||(a[3]=o=>i.onDateFromChanged())},{default:n(()=>[u(" Begin Date (Default is the Creation/ First Invoice Date) ")]),_:1},8,["modelValue","error"])):c("",!0)]),t("div",J,[r(p).includes("update vends")?(m(),g(w,{key:0,modelValue:e.value.termination_date,"onUpdate:modelValue":a[4]||(a[4]=o=>e.value.termination_date=o),error:e.value.errors.termination_date,minDate:e.value.begin_date},{default:n(()=>[u(" Termination Date (Default is the Unbinding Date from CMS, status change) ")]),_:1},8,["modelValue","error","minDate"])):c("",!0)]),t("div",L,[d(V,{modelValue:e.value.private_key,"onUpdate:modelValue":a[5]||(a[5]=o=>e.value.private_key=o),error:e.value.errors.private_key,disabled:!r(p).includes("update vends")},{default:n(()=>[u(" Private Key ")]),_:1},8,["modelValue","error","disabled"])])]),t("div",Q,[t("div",W,[d(x,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:a[6]||(a[6]=o=>i.$emit("modalClose")),form:"submit"},{default:n(()=>[d(r(M),{class:"w-4 h-4"}),X]),_:1}),s.vend.latestVendBinding&&s.vend.latestVendBinding.customer&&r(p).includes("update vends")?(m(),g(x,{key:0,type:"button",class:"bg-yellow-500 hover:bg-yellow-600 text-white flex space-x-1",onClick:a[7]||(a[7]=o=>i.unbindCustomer(e.value.id))},{default:n(()=>[d(r(F),{class:"w-4 h-4"}),Y]),_:1})):c("",!0),r(p).includes("update vends")?(m(),g(x,{key:1,type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:n(()=>[d(r(O),{class:"w-4 h-4"}),ee]),_:1})):c("",!0)])])],32)])])])])]),_:1})],64))}};export{pe as default};
