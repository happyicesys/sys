import{o as i,g as V,d as t,i as y,u as m,j as w,c as g,a as o,w as l,T as B,t as k,p as b,b as c,e as C,f}from"./app.a148a0ce.js";import{_ as v}from"./Button.d03094fa.js";import{_ as p}from"./FormInput.171dd35c.js";import{_ as S}from"./Modal.08e1db9d.js";import{r as $}from"./ArrowUturnLeftIcon.1f62befc.js";import{r as M}from"./CheckCircleIcon.e4e45e06.js";import"./open-closed.ce385b24.js";function N(a,u){return i(),V("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[t("path",{"fill-rule":"evenodd",d:"M2.232 12.207a.75.75 0 011.06.025l3.958 4.146V6.375a5.375 5.375 0 0110.75 0V9.25a.75.75 0 01-1.5 0V6.375a3.875 3.875 0 00-7.75 0v10.003l3.957-4.146a.75.75 0 011.085 1.036l-5.25 5.5a.75.75 0 01-1.085 0l-5.25-5.5a.75.75 0 01.025-1.06z","clip-rule":"evenodd"})])}const j={class:"flex flex-col md:flex-row space-x-2"},U=t("span",{class:"text-gray-600"}," Editing ",-1),E={key:0},F=["onSubmit"],T={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6 pb-2"},D={class:"sm:col-span-6"},O=f(" Name "),q={class:"sm:col-span-6"},z=f(" Serial Number "),K={class:"sm:col-span-6"},P=f(" Private Key "),A={class:"sm:col-span-6"},G={class:"flex space-x-1 mt-5 justify-end"},H=t("span",null," Back ",-1),I=t("span",null," Unbind ",-1),J=t("span",null," Save ",-1),ee={__name:"Form",props:{vend:Object,countries:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(a,{emit:u}){const r=a,e=y(m(_()));w(()=>{e.value=r.vend?m(r.vend):m(_()),e.value.name=r.vend.latestVendBinding?r.vend.latestVendBinding.customer.code+"    "+r.vend.latestVendBinding.customer.name:r.vend.name});function _(){return{name:"",serial_num:"",private_key:""}}function h(){e.value.clearErrors(),e.value.post("/vends/"+e.value.id+"/update",{onSuccess:()=>{u("modalClose")},preserveState:!0,replace:!0})}function x(d){e.value.post("/vends/"+d+"/unbind",{onSuccess:()=>{u("modalClose")},preserveState:!0,replace:!0})}return(d,s)=>(i(),g(B,{to:"body"},[o(S,{open:a.showModal,onModalClose:s[5]||(s[5]=n=>d.$emit("modalClose"))},{header:l(()=>[t("div",j,[U,a.vend?(i(),V("span",E,k(a.vend.code),1)):b("",!0)])]),default:l(()=>[t("form",{onSubmit:C(h,["prevent"]),id:"submit"},[t("div",T,[t("div",D,[o(p,{modelValue:e.value.name,"onUpdate:modelValue":s[0]||(s[0]=n=>e.value.name=n),error:e.value.errors.name,required:"true",disabled:a.vend.latestVendBinding&&a.vend.latestVendBinding.customer},{default:l(()=>[O]),_:1},8,["modelValue","error","disabled"])]),t("div",q,[o(p,{modelValue:e.value.serial_num,"onUpdate:modelValue":s[1]||(s[1]=n=>e.value.serial_num=n),error:e.value.errors.serial_num},{default:l(()=>[z]),_:1},8,["modelValue","error"])]),t("div",K,[o(p,{modelValue:e.value.private_key,"onUpdate:modelValue":s[2]||(s[2]=n=>e.value.private_key=n),error:e.value.errors.private_key},{default:l(()=>[P]),_:1},8,["modelValue","error"])])]),t("div",A,[t("div",G,[o(v,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:s[3]||(s[3]=n=>d.$emit("modalClose")),form:"submit"},{default:l(()=>[o(c($),{class:"w-4 h-4"}),H]),_:1}),a.vend.latestVendBinding&&a.vend.latestVendBinding.customer?(i(),g(v,{key:0,type:"button",class:"bg-yellow-500 hover:bg-yellow-600 text-white flex space-x-1",onClick:s[4]||(s[4]=n=>x(e.value.id))},{default:l(()=>[o(c(N),{class:"w-4 h-4"}),I]),_:1})):b("",!0),o(v,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:l(()=>[o(c(M),{class:"w-4 h-4"}),J]),_:1})])])],40,F)]),_:1},8,["open"])]))}};export{ee as default};
