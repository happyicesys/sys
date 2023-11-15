import{g as x,T as l,h as y,c as C,a,w as r,p as b,o as d,b as e,f as _,l as g,t as i,d as w,u as h,e as E}from"./app.baff8b32.js";import{_ as f}from"./Button.ccd018db.js";import{_ as S}from"./FormInput.986fd714.js";import{_ as $}from"./Modal.3fbc64a3.js";import{r as k}from"./ArrowUturnLeftIcon.ef3b9238.js";import{r as V}from"./CheckCircleIcon.a59a4ba5.js";import"./open-closed.39849135.js";import"./disposables.b1ba51d4.js";const B={class:"flex flex-col md:flex-row space-x-2"},M={key:0,class:"text-gray-600"},N={key:1},D=["onSubmit"],T={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},j={class:"sm:col-span-6"},F=e("h3",{class:"text-base font-medium leading-7 text-gray-900"}," Code ",-1),O={class:"mt-1 text-sm leading-6 text-gray-700"},U={class:"sm:col-span-6"},W=e("h3",{class:"text-base font-medium leading-7 text-gray-900"}," Desc ",-1),q={class:"mt-1 text-sm leading-6 text-gray-700"},z={class:"sm:col-span-6"},A={class:"sm:col-span-6"},G={class:"flex space-x-1 mt-5 justify-end"},H=e("span",null," Back ",-1),I=e("span",null," Save ",-1),Z={__name:"Form",props:{vendChannelError:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(c,{emit:m}){const t=c,s=x(l(u()));y(()=>{s.value=t.vendChannelError?l(t.vendChannelError):l(u())});function u(){return{code:"",desc:"",weightage:""}}function v(){s.value.clearErrors(),t.type==="create"&&s.value.post("/vend-channel-errors/create",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0}),t.type==="update"&&s.value.post("/vend-channel-errors/"+s.value.id+"/update",{onSuccess:()=>{m("modalClose")},preserveState:!0,replace:!0})}return(p,o)=>(d(),C(b,{to:"body"},[a($,{open:c.showModal,onModalClose:o[2]||(o[2]=n=>p.$emit("modalClose"))},{header:r(()=>[e("div",B,[t.vendChannelError?(d(),_("span",M," Editing ")):g("",!0),t.vendChannelError?(d(),_("span",N,i(t.vendChannelError.name),1)):g("",!0)])]),default:r(()=>[e("form",{onSubmit:E(v,["prevent"]),id:"submit"},[e("div",T,[e("div",j,[F,e("p",O,i(s.value.code),1)]),e("div",U,[W,e("p",q,i(s.value.desc),1)]),e("div",z,[a(S,{modelValue:s.value.weightage,"onUpdate:modelValue":o[0]||(o[0]=n=>s.value.weightage=n),error:s.value.errors.weightage},{default:r(()=>[w(" Weightage (%) ")]),_:1},8,["modelValue","error"])])]),e("div",A,[e("div",G,[a(f,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:o[1]||(o[1]=n=>p.$emit("modalClose")),form:"submit"},{default:r(()=>[a(h(k),{class:"w-4 h-4"}),H]),_:1}),a(f,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[a(h(V),{class:"w-4 h-4"}),I]),_:1})])])],40,D)]),_:1},8,["open"])]))}};export{Z as default};
