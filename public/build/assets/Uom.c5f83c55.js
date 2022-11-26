import{i as n,u,j as U,o as _,c as V,a as l,w as d,T as k,d as e,g as B,t as C,p as M,q as v,v as f,e as x,b,f as $}from"./app.c318dc46.js";import{_ as g}from"./Button.27f88839.js";import{_ as O,a as S,r as j}from"./Modal.6db78482.js";import{_ as N}from"./MultiSelect.8217c9f4.js";import{r as T}from"./ArrowUturnLeftIcon.f4e69ee0.js";import"./open-closed.4eec9221.js";const q=e("div",{class:"flex flex-col md:flex-row space-x-2"},[e("span",{class:"text-gray-600"}," Bind New Uom ")],-1),D=["onSubmit"],I={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},E={class:"sm:col-span-6"},F=e("div",{class:"flex space-x-1"},[e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," UOM "),e("span",{class:"text-red-500"}," * ")],-1),P={key:0,class:"text-sm text-red-600"},z={class:"sm:col-span-6"},A=$(" Value (equals how many Base UOM) "),G={class:"sm:col-span-6 pt-2"},H={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 md:space-x-4"},J={class:"relative flex items-start"},K={class:"flex h-5 items-center"},L=e("div",{class:"ml-3 text-sm"},[e("label",{for:"candidates",class:"font-medium text-gray-700"},"Is Base UOM?")],-1),Q={class:"relative flex items-start"},R={class:"flex h-5 items-center"},W=e("div",{class:"ml-3 text-sm"},[e("label",{for:"candidates",class:"font-medium text-gray-700"},"Is Transacted UOM?")],-1),X={class:"sm:col-span-6"},Y={class:"flex space-x-1 mt-5 justify-end"},Z=e("span",null," Back ",-1),ee=e("span",null," Save ",-1),de={__name:"Uom",props:{product:Object,uoms:Object,showModal:Boolean},emits:["modalClose"],setup(c,{emit:h}){const i=c,s=n(u(p())),r=n([]),m=n([]);U(()=>{s.value=i.productUom?u(i.productUom):u(p()),r.value=i.uoms.data.map(o=>({id:o.id,name:o.name})),m.value=i.product.productUoms.map(o=>o.uom.id),r.value=r.value.filter(o=>!m.value.includes(o.id))});function p(){return{uom_id:"",value:"",is_base_uom:!1,is_transaction_uom:!1}}function y(){s.value.clearErrors(),s.value.transform(o=>({...o,uom_id:o.uom_id.id})).post("/products/"+i.product.id+"/uom-binding",{onSuccess:()=>{h("modalClose")},preserveState:!0,resetOnSuccess:!0,replace:!0})}function w(){s.value.value=1}return(o,t)=>(_(),V(k,{to:"body"},[l(O,{open:c.showModal,onModalClose:t[5]||(t[5]=a=>o.$emit("modalClose"))},{header:d(()=>[q]),default:d(()=>[e("form",{onSubmit:x(y,["prevent"]),id:"submit"},[e("div",I,[e("div",E,[F,l(N,{modelValue:s.value.uom_id,"onUpdate:modelValue":t[0]||(t[0]=a=>s.value.uom_id=a),options:r.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),s.value.errors.uom_id?(_(),B("div",P,C(s.value.errors.uom_id),1)):M("",!0)]),e("div",z,[l(S,{modelValue:s.value.value,"onUpdate:modelValue":t[1]||(t[1]=a=>s.value.value=a),error:s.value.errors.value,required:"true",disabled:s.value.is_base_uom},{default:d(()=>[A]),_:1},8,["modelValue","error","disabled"])]),e("div",G,[e("div",H,[e("div",J,[e("div",K,[v(e("input",{id:"candidates","onUpdate:modelValue":t[2]||(t[2]=a=>s.value.is_base_uom=a),type:"checkbox",class:"h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-75",onClick:w},null,512),[[f,s.value.is_base_uom]])]),L]),e("div",Q,[e("div",R,[v(e("input",{id:"candidates","onUpdate:modelValue":t[3]||(t[3]=a=>s.value.is_transaction_uom=a),type:"checkbox",class:"h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-75"},null,512),[[f,s.value.is_transaction_uom]])]),W])])])]),e("div",X,[e("div",Y,[l(g,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:t[4]||(t[4]=x(a=>o.$emit("modalClose"),["prevent"]))},{default:d(()=>[l(b(T),{class:"w-4 h-4"}),Z]),_:1}),l(g,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:d(()=>[l(b(j),{class:"w-4 h-4"}),ee]),_:1})])])],40,D)]),_:1},8,["open"])]))}};export{de as default};
