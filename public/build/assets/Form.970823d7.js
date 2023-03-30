import{o as r,g as V,d as t,i as y,u as m,j as w,c,a as o,w as l,T as B,t as k,p as v,b as p,e as C,f as b}from"./app.d959e322.js";import{_ as f}from"./Button.4f45a43f.js";import{_ as g}from"./FormInput.68e39cd7.js";import{_ as S}from"./Modal.9a23b1f2.js";import{r as $}from"./ArrowUturnLeftIcon.219f30fa.js";import{r as M}from"./CheckCircleIcon.bc771892.js";import"./open-closed.2b67243e.js";function N(s,u){return r(),V("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[t("path",{"fill-rule":"evenodd",d:"M2.232 12.207a.75.75 0 011.06.025l3.958 4.146V6.375a5.375 5.375 0 0110.75 0V9.25a.75.75 0 01-1.5 0V6.375a3.875 3.875 0 00-7.75 0v10.003l3.957-4.146a.75.75 0 011.085 1.036l-5.25 5.5a.75.75 0 01-1.085 0l-5.25-5.5a.75.75 0 01.025-1.06z","clip-rule":"evenodd"})])}const j={class:"flex flex-col md:flex-row space-x-2"},U=t("span",{class:"text-gray-600"}," Editing ",-1),E={key:0},F=["onSubmit"],O={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6 pb-2"},T={class:"sm:col-span-6"},D=b(" Name "),q={class:"sm:col-span-6"},z=b(" Serial Number "),A={class:"sm:col-span-6"},K=b(" Private Key "),P={class:"sm:col-span-6"},G={class:"flex space-x-1 mt-5 justify-end"},H=t("span",null," Back ",-1),I=t("span",null," Unbind ",-1),J=t("span",null," Save ",-1),ee={__name:"Form",props:{vend:Object,countries:Object,permissions:[Array,Object],type:String,showModal:Boolean},emits:["modalClose"],setup(s,{emit:u}){const d=s,e=y(m(_()));w(()=>{e.value=d.vend?m(d.vend):m(_()),e.value.name=d.vend.latestVendBinding?d.vend.latestVendBinding.customer.code+"    "+d.vend.latestVendBinding.customer.name:d.vend.name});function _(){return{name:"",serial_num:"",private_key:""}}function h(){e.value.clearErrors(),e.value.post("/vends/"+e.value.id+"/update",{onSuccess:()=>{u("modalClose")},preserveState:!0,replace:!0})}function x(i){e.value.post("/vends/"+i+"/unbind",{onSuccess:()=>{u("modalClose")},preserveState:!0,replace:!0})}return(i,a)=>(r(),c(B,{to:"body"},[o(S,{open:s.showModal,onModalClose:a[5]||(a[5]=n=>i.$emit("modalClose"))},{header:l(()=>[t("div",j,[U,s.vend?(r(),V("span",E,k(s.vend.code),1)):v("",!0)])]),default:l(()=>[t("form",{onSubmit:C(h,["prevent"]),id:"submit"},[t("div",O,[t("div",T,[o(g,{modelValue:e.value.name,"onUpdate:modelValue":a[0]||(a[0]=n=>e.value.name=n),error:e.value.errors.name,required:"true",disabled:s.vend.latestVendBinding&&s.vend.latestVendBinding.customer},{default:l(()=>[D]),_:1},8,["modelValue","error","disabled"])]),t("div",q,[o(g,{modelValue:e.value.serial_num,"onUpdate:modelValue":a[1]||(a[1]=n=>e.value.serial_num=n),error:e.value.errors.serial_num,disabled:!s.permissions.includes("update vends")},{default:l(()=>[z]),_:1},8,["modelValue","error","disabled"])]),t("div",A,[o(g,{modelValue:e.value.private_key,"onUpdate:modelValue":a[2]||(a[2]=n=>e.value.private_key=n),error:e.value.errors.private_key,disabled:!s.permissions.includes("update vends")},{default:l(()=>[K]),_:1},8,["modelValue","error","disabled"])])]),t("div",P,[t("div",G,[o(f,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:a[3]||(a[3]=n=>i.$emit("modalClose")),form:"submit"},{default:l(()=>[o(p($),{class:"w-4 h-4"}),H]),_:1}),s.vend.latestVendBinding&&s.vend.latestVendBinding.customer&&s.permissions.includes("update vends")?(r(),c(f,{key:0,type:"button",class:"bg-yellow-500 hover:bg-yellow-600 text-white flex space-x-1",onClick:a[4]||(a[4]=n=>x(e.value.id))},{default:l(()=>[o(p(N),{class:"w-4 h-4"}),I]),_:1})):v("",!0),s.permissions.includes("update vends")?(r(),c(f,{key:1,type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:l(()=>[o(p(M),{class:"w-4 h-4"}),J]),_:1})):v("",!0)])])],40,F)]),_:1},8,["open"])]))}};export{ee as default};