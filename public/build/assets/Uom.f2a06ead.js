import{g as n,T as m,h as V,c as k,a as l,w as d,p as B,o as _,b as e,f as C,t as M,l as $,d as O,m as v,v as f,e as x,u as b}from"./app.6c1fd100.js";import{_ as g}from"./Button.c220b9da.js";import{_ as S}from"./FormInput.80b139a0.js";import{_ as N}from"./MultiSelect.1ef9ae33.js";import{_ as j}from"./Modal.6dbafd54.js";import{r as T}from"./ArrowUturnLeftIcon.0e5d2ed7.js";import{r as D}from"./CheckCircleIcon.d3a38b3a.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.200ceaf2.js";import"./keyboard.a01f6322.js";import"./disposables.3f9ca8af.js";const I=e("div",{class:"flex flex-col md:flex-row space-x-2"},[e("span",{class:"text-gray-600"}," Bind New Uom ")],-1),q={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},E={class:"sm:col-span-6"},F=e("div",{class:"flex space-x-1"},[e("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," UOM "),e("span",{class:"text-red-500"}," * ")],-1),P={key:0,class:"text-sm text-red-600"},z={class:"sm:col-span-6"},A={class:"sm:col-span-6 pt-2"},G={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 md:space-x-4"},H={class:"relative flex items-start"},J={class:"flex h-5 items-center"},K=e("div",{class:"ml-3 text-sm"},[e("label",{for:"candidates",class:"font-medium text-gray-700"},"Is Base UOM?")],-1),L={class:"relative flex items-start"},Q={class:"flex h-5 items-center"},R=e("div",{class:"ml-3 text-sm"},[e("label",{for:"candidates",class:"font-medium text-gray-700"},"Is Transacted UOM?")],-1),W={class:"sm:col-span-6"},X={class:"flex space-x-1 mt-5 justify-end"},Y=e("span",null," Back ",-1),Z=e("span",null," Save ",-1),me={__name:"Uom",props:{product:Object,uoms:Object,showModal:Boolean},emits:["modalClose"],setup(u,{emit:h}){const i=u,y=h,s=n(m(p())),r=n([]),c=n([]);V(()=>{s.value=i.productUom?m(i.productUom):m(p()),r.value=i.uoms.data.map(o=>({id:o.id,name:o.name})),c.value=i.product.productUoms.map(o=>o.uom.id),r.value=r.value.filter(o=>!c.value.includes(o.id))});function p(){return{uom_id:"",value:"",is_base_uom:!1,is_transaction_uom:!1}}function w(){s.value.clearErrors(),s.value.transform(o=>({...o,uom_id:o.uom_id.id})).post("/products/"+i.product.id+"/uom-binding",{onSuccess:()=>{y("modalClose")},preserveState:!0,resetOnSuccess:!0,replace:!0})}function U(){s.value.value=1}return(o,t)=>(_(),k(B,{to:"body"},[l(j,{open:u.showModal,onModalClose:t[5]||(t[5]=a=>o.$emit("modalClose"))},{header:d(()=>[I]),default:d(()=>[e("form",{onSubmit:x(w,["prevent"]),id:"submit"},[e("div",q,[e("div",E,[F,l(N,{modelValue:s.value.uom_id,"onUpdate:modelValue":t[0]||(t[0]=a=>s.value.uom_id=a),options:r.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),s.value.errors.uom_id?(_(),C("div",P,M(s.value.errors.uom_id),1)):$("",!0)]),e("div",z,[l(S,{modelValue:s.value.value,"onUpdate:modelValue":t[1]||(t[1]=a=>s.value.value=a),error:s.value.errors.value,required:"true",disabled:s.value.is_base_uom},{default:d(()=>[O(" Value (equals how many Base UOM) ")]),_:1},8,["modelValue","error","disabled"])]),e("div",A,[e("div",G,[e("div",H,[e("div",J,[v(e("input",{id:"candidates","onUpdate:modelValue":t[2]||(t[2]=a=>s.value.is_base_uom=a),type:"checkbox",class:"h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-75",onClick:U},null,512),[[f,s.value.is_base_uom]])]),K]),e("div",L,[e("div",Q,[v(e("input",{id:"candidates","onUpdate:modelValue":t[3]||(t[3]=a=>s.value.is_transaction_uom=a),type:"checkbox",class:"h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-75"},null,512),[[f,s.value.is_transaction_uom]])]),R])])])]),e("div",W,[e("div",X,[l(g,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:t[4]||(t[4]=x(a=>o.$emit("modalClose"),["prevent"]))},{default:d(()=>[l(b(T),{class:"w-4 h-4"}),Y]),_:1}),l(g,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:d(()=>[l(b(D),{class:"w-4 h-4"}),Z]),_:1})])])],32)]),_:1},8,["open"])]))}};export{me as default};
