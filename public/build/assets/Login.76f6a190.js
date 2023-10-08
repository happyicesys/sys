import{j as h,m as w,v as x,o as i,f as _,T as k,c,w as m,a,u as s,Z as v,t as y,l as u,b as r,i as V,d as p,n as $,e as B}from"./app.009697ae.js";import{_ as C}from"./Button.37f68050.js";import{_ as U}from"./Guest.f6827668.js";import{_ as N,a as f,b as g}from"./ValidationErrors.e516da4e.js";import"./_plugin-vue_export-helper.cdc0426e.js";const S=["value"],q={__name:"Checkbox",props:{checked:{type:[Array,Boolean],default:!1},value:{default:null}},emits:["update:checked"],setup(l,{emit:e}){const d=l,n=h({get(){return d.checked},set(t){e("update:checked",t)}});return(t,o)=>w((i(),_("input",{type:"checkbox",value:l.value,"onUpdate:modelValue":o[0]||(o[0]=b=>n.value=b),class:"rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"},null,8,S)),[[x,n.value]])}},L={key:0,class:"mb-4 font-medium text-sm text-green-600"},P=["onSubmit"],R={class:"mt-4"},j={class:"block mt-4"},D={class:"flex items-center"},E=r("span",{class:"ml-2 text-sm text-gray-600"},"Remember me",-1),F={class:"flex items-center justify-end mt-4"},G={__name:"Login",props:{canResetPassword:Boolean,status:String},setup(l){const e=k({login:"",password:"",remember:!1}),d=()=>{e.post(route("login"),{onFinish:()=>e.reset("password")})};return(n,t)=>(i(),c(U,null,{default:m(()=>[a(s(v),{title:"Log in"}),a(N,{class:"mb-4"}),l.status?(i(),_("div",L,y(l.status),1)):u("",!0),r("form",{onSubmit:B(d,["prevent"])},[r("div",null,[a(f,{for:"login",value:"Email or Username"}),a(g,{id:"login",type:"text",class:"mt-1 block w-full",modelValue:s(e).login,"onUpdate:modelValue":t[0]||(t[0]=o=>s(e).login=o),required:"",autofocus:"",autocomplete:"username"},null,8,["modelValue"])]),r("div",R,[a(f,{for:"password",value:"Password"}),a(g,{id:"password",type:"password",class:"mt-1 block w-full",modelValue:s(e).password,"onUpdate:modelValue":t[1]||(t[1]=o=>s(e).password=o),required:"",autocomplete:"current-password"},null,8,["modelValue"])]),r("div",j,[r("label",D,[a(q,{name:"remember",checked:s(e).remember,"onUpdate:checked":t[2]||(t[2]=o=>s(e).remember=o)},null,8,["checked"]),E])]),r("div",F,[l.canResetPassword?(i(),c(s(V),{key:0,href:n.route("password.request"),class:"underline text-sm text-gray-600 hover:text-gray-900"},{default:m(()=>[p(" Forgot your password? ")]),_:1},8,["href"])):u("",!0),a(C,{class:$(["ml-4 border-transparent bg-indigo-600 py-2 text-sm font-medium leading-4 text-white shadow-sm hover:bg-indigo-700 px-3",{"opacity-25":s(e).processing}]),disabled:s(e).processing},{default:m(()=>[p(" Log in ")]),_:1},8,["class","disabled"])])],40,P)]),_:1}))}};export{G as default};
