import{j as w,m as x,v as k,o as n,f as b,T as v,c as u,w as m,a,u as s,Z as y,t as V,l as p,b as r,i as $,d as f,n as B,e as C}from"./app.242e5fba.js";import{_ as U}from"./Button.be945d63.js";import{_ as N}from"./Guest.ce82c0aa.js";import{_ as q,a as g,b as _}from"./ValidationErrors.d1630069.js";import"./_plugin-vue_export-helper.cdc0426e.js";const L=["value"],P={__name:"Checkbox",props:{checked:{type:[Array,Boolean],default:!1},value:{default:null}},emits:["update:checked"],setup(l,{emit:e}){const i=e,d=l,t=w({get(){return d.checked},set(o){i("update:checked",o)}});return(o,c)=>x((n(),b("input",{type:"checkbox",value:l.value,"onUpdate:modelValue":c[0]||(c[0]=h=>t.value=h),class:"rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"},null,8,L)),[[k,t.value]])}},R={key:0,class:"mb-4 font-medium text-sm text-green-600"},S={class:"mt-4"},j={class:"block mt-4"},D={class:"flex items-center"},E=r("span",{class:"ml-2 text-sm text-gray-600"},"Remember me",-1),F={class:"flex items-center justify-end mt-4"},G={__name:"Login",props:{canResetPassword:Boolean,status:String},setup(l){const e=v({login:"",password:"",remember:!1}),i=()=>{e.post(route("login"),{onFinish:()=>e.reset("password")})};return(d,t)=>(n(),u(N,null,{default:m(()=>[a(s(y),{title:"Log in"}),a(q,{class:"mb-4"}),l.status?(n(),b("div",R,V(l.status),1)):p("",!0),r("form",{onSubmit:C(i,["prevent"])},[r("div",null,[a(g,{for:"login",value:"Email or Username"}),a(_,{id:"login",type:"text",class:"mt-1 block w-full",modelValue:s(e).login,"onUpdate:modelValue":t[0]||(t[0]=o=>s(e).login=o),required:"",autofocus:"",autocomplete:"username"},null,8,["modelValue"])]),r("div",S,[a(g,{for:"password",value:"Password"}),a(_,{id:"password",type:"password",class:"mt-1 block w-full",modelValue:s(e).password,"onUpdate:modelValue":t[1]||(t[1]=o=>s(e).password=o),required:"",autocomplete:"current-password"},null,8,["modelValue"])]),r("div",j,[r("label",D,[a(P,{name:"remember",checked:s(e).remember,"onUpdate:checked":t[2]||(t[2]=o=>s(e).remember=o)},null,8,["checked"]),E])]),r("div",F,[l.canResetPassword?(n(),u(s($),{key:0,href:d.route("password.request"),class:"underline text-sm text-gray-600 hover:text-gray-900"},{default:m(()=>[f(" Forgot your password? ")]),_:1},8,["href"])):p("",!0),a(U,{class:B(["ml-4 border-transparent bg-indigo-600 py-2 text-sm font-medium leading-4 text-white shadow-sm hover:bg-indigo-700 px-3",{"opacity-25":s(e).processing}]),disabled:s(e).processing},{default:m(()=>[f(" Log in ")]),_:1},8,["class","disabled"])])],32)]),_:1}))}};export{G as default};
