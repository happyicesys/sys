import{g as U,o as i,c as V,w as r,b as t,a as l,u as p,r as $,f as v,l as f,F as N,k as C,t as g,T as k,h as B,q as A,d as n,e as j}from"./app.2e671246.js";import{_ as S}from"./Button.6e95bfad.js";import{_ as u}from"./FormInput.057612aa.js";import{_ as D}from"./Modal.01f30ff8.js";import{_ as w}from"./MultiSelect.7c5551be.js";import{H as E,$ as q,K as L,r as P,U as T,_ as I,N as O}from"./MagnifyingGlassCircleIcon.5626299e.js";import{r as M}from"./ArrowUturnLeftIcon.4f8563da.js";import{r as F}from"./CheckCircleIcon.84dcd428.js";import"./open-closed.94268395.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.8b62ab31.js";import"./use-resolve-button-type.2fbd73ae.js";const G={class:"flex space-x-1"},K={key:0,class:"text-red-500"},H={class:"relative mt-1"},R=["onClick"],Y={class:"block truncate"},z={__name:"SearchAddressInput",props:{modelValue:String,required:[Boolean,String]},emits:["update:modelValue","selected"],setup(y,{emit:b}){const d=U([]),e=_.debounce(async m=>{const x="https://developers.onemap.sg/commonapi/search?searchVal="+m.target.value+"&returnGeom=Y&getAddrDetails=Y";let a=await(await fetch(x)).json();a&&(d.value=await a.results)},300);function c(m){b("update:modelValue",m.target.value),e(m)}function h(m){b("selected",m)}return(m,x)=>(i(),V(p(O),{as:"div"},{default:r(()=>[t("div",G,[l(p(E),{class:"block text-sm font-medium text-gray-700"},{default:r(()=>[$(m.$slots,"default")]),_:3}),y.required?(i(),v("span",K," * ")):f("",!0)]),t("div",H,[l(p(q),{class:"w-full rounded-md border border-gray-300 bg-white py-2 pl-3 pr-10 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm",onInput:c,value:y.modelValue},null,8,["value"]),l(p(L),{class:"absolute inset-y-0 right-0 flex items-center rounded-r-md px-2 focus:outline-none"},{default:r(()=>[l(p(P),{class:"h-5 w-5 text-gray-400","aria-hidden":"true"})]),_:1}),d.value.length>0?(i(),V(p(T),{key:0,class:"absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm"},{default:r(()=>[(i(!0),v(N,null,C(d.value,a=>(i(),V(p(I),{as:"template"},{default:r(()=>[t("li",{class:"relative cursor-default select-none py-2 pl-3 pr-9 hover:bg-gray-100",onClick:s=>h(a)},[t("span",Y,g(a.ADDRESS),1)],8,R)]),_:2},1024))),256))]),_:1})):f("",!0)])]),_:3}))}},J={class:"flex flex-col md:flex-row space-x-2"},Q={key:0,class:"text-gray-600"},W={key:1},X={key:2,class:"text-gray-600"},Z=["onSubmit"],ee={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},te={class:"sm:col-span-4"},se={class:"sm:col-span-2"},oe={class:"sm:col-span-3"},ae={class:"sm:col-span-3"},le=t("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Base Currency ",-1),re={key:0,class:"text-sm text-red-600"},de=t("div",{class:"sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3"},[t("div",{class:"relative"},[t("div",{class:"absolute inset-0 flex items-center","aria-hidden":"true"},[t("div",{class:"w-full border-t border-gray-300"})]),t("div",{class:"relative flex justify-center"},[t("span",{class:"px-4 bg-white text-lg font-medium text-gray-900"}," Contact ")])])],-1),ne={class:"sm:col-span-3"},ue={class:"sm:col-span-3"},ie={class:"sm:col-span-2"},ce=t("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Phone Code ",-1),me={key:0,class:"text-sm text-red-600"},_e={class:"sm:col-span-4"},pe=t("div",{class:"sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3"},[t("div",{class:"relative"},[t("div",{class:"absolute inset-0 flex items-center","aria-hidden":"true"},[t("div",{class:"w-full border-t border-gray-300"})]),t("div",{class:"relative flex justify-center"},[t("span",{class:"px-3 bg-white text-lg font-medium text-gray-900"}," Address ")])])],-1),ve={class:"sm:col-span-6"},fe={class:"sm:col-span-3"},ye={class:"sm:col-span-3"},be={class:"sm:col-span-3"},ge={class:"sm:col-span-3"},he={class:"sm:col-span-3"},xe=t("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Country ",-1),Ve={key:0,class:"text-sm text-red-600"},ke={class:"sm:col-span-3 hidden"},we={class:"sm:col-span-3 hidden"},Ue={class:"sm:col-span-6"},Se={class:"flex space-x-1 mt-5 justify-end"},$e=t("span",null," Back ",-1),Ne=t("span",null," Save ",-1),Oe={__name:"Form",props:{profile:Object,countries:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(y,{emit:b}){const d=y,e=U(k(h())),c=U([]);B(()=>{c.value=d.countries.data,e.value=d.profile?k(d.profile):k(h()),d.type==="create"&&(e.value.base_currency_id=e.value.base_currency_id?e.value.base_currency_id:c.value[0],e.value.address.country_id=e.value.address.country_id?e.value.address.country_id:c.value[0],e.value.contact.phone_country_id=e.value.contact.phone_country_id?e.value.contact.phone_country_id:c.value[0])});function h(){return{alias:"",name:"",uen:"",base_currency_id:"",address:{block_num:"",building:"",country_id:"",latitude:"",longitude:"",postcode:"",street_name:"",unit_num:""},contact:{name:"",email:"",phone_country_id:"",phone_num:""}}}function m(a){e.value.address={block_num:a.BLK_NO,building:a.BUILDING,country_id:c.value[0],latitude:a.LATITUDE,longitude:a.LONGTITUDE,postcode:a.POSTAL,street_name:a.ROAD_NAME,unit_num:""}}function x(){e.value.clearErrors(),d.type==="create"&&e.value.transform(a=>({...a,base_currency_id:a.base_currency_id.id,address:{...a.address,country_id:a.address.country_id.id},contact:{...a.contact,phone_country_id:a.contact.phone_country_id.id}})).post("/profiles/create",{onSuccess:()=>{b("modalClose")},preserveState:!0,replace:!0}),d.type==="update"&&e.value.transform(a=>({...a,base_currency_id:a.base_currency_id.id,address:{...a.address,country_id:a.address.country_id.id},contact:{...a.contact,phone_country_id:a.contact.phone_country_id.id}})).post("/profiles/"+e.value.id+"/update",{onSuccess:()=>{b("modalClose")},preserveState:!0,replace:!0})}return(a,s)=>(i(),V(A,{to:"body"},[l(D,{open:y.showModal,onModalClose:s[17]||(s[17]=o=>a.$emit("modalClose"))},{header:r(()=>[t("div",J,[d.profile?(i(),v("span",Q," Editing ")):f("",!0),d.profile?(i(),v("span",W,g(d.profile.name),1)):(i(),v("span",X," Create New Profile "))])]),default:r(()=>[t("form",{onSubmit:j(x,["prevent"]),id:"submit"},[t("div",ee,[t("div",te,[l(u,{modelValue:e.value.name,"onUpdate:modelValue":s[0]||(s[0]=o=>e.value.name=o),error:e.value.errors.name,required:"true"},{default:r(()=>[n(" Name ")]),_:1},8,["modelValue","error"])]),t("div",se,[l(u,{modelValue:e.value.alias,"onUpdate:modelValue":s[1]||(s[1]=o=>e.value.alias=o),error:e.value.errors.alias},{default:r(()=>[n(" Alias ")]),_:1},8,["modelValue","error"])]),t("div",oe,[l(u,{modelValue:e.value.uen,"onUpdate:modelValue":s[2]||(s[2]=o=>e.value.uen=o),error:e.value.errors.uen},{default:r(()=>[n(" UEN ")]),_:1},8,["modelValue","error"])]),t("div",ae,[le,l(w,{modelValue:e.value.base_currency_id,"onUpdate:modelValue":s[3]||(s[3]=o=>e.value.base_currency_id=o),options:c.value,trackBy:"id",valueProp:"id",label:"currency_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),e.value.errors.base_currency_id?(i(),v("div",re,g(e.value.errors.base_currency_id),1)):f("",!0)]),de,t("div",ne,[l(u,{modelValue:e.value.contact.name,"onUpdate:modelValue":s[4]||(s[4]=o=>e.value.contact.name=o),error:e.value.errors["contact.name"]},{default:r(()=>[n(" Name ")]),_:1},8,["modelValue","error"])]),t("div",ue,[l(u,{modelValue:e.value.contact.email,"onUpdate:modelValue":s[5]||(s[5]=o=>e.value.contact.email=o),error:e.value.errors["contact.email"]},{default:r(()=>[n(" Email ")]),_:1},8,["modelValue","error"])]),t("div",ie,[ce,l(w,{modelValue:e.value.contact.phone_country_id,"onUpdate:modelValue":s[6]||(s[6]=o=>e.value.contact.phone_country_id=o),options:c.value,trackBy:"id",valueProp:"id",label:"phone_code",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),e.value.errors["contact.phone_country_id"]?(i(),v("div",me,g(e.value.errors["contact.phone_country_id"]),1)):f("",!0)]),t("div",_e,[l(u,{modelValue:e.value.contact.phone_num,"onUpdate:modelValue":s[7]||(s[7]=o=>e.value.contact.phone_num=o),required:"true",error:e.value.errors["contact.phone_num"]},{default:r(()=>[n(" Phone Number ")]),_:1},8,["modelValue","error"])]),pe,t("div",ve,[l(z,{modelValue:e.value.address.postcode,"onUpdate:modelValue":s[8]||(s[8]=o=>e.value.address.postcode=o),onSelected:m,required:"true",error:e.value.errors["address.postcode"]},{default:r(()=>[n(" Postcode ")]),_:1},8,["modelValue","error"])]),t("div",fe,[l(u,{modelValue:e.value.address.unit_num,"onUpdate:modelValue":s[9]||(s[9]=o=>e.value.address.unit_num=o),required:"true",error:e.value.errors["address.unit_num"]},{default:r(()=>[n(" Unit Num ")]),_:1},8,["modelValue","error"])]),t("div",ye,[l(u,{modelValue:e.value.address.block_num,"onUpdate:modelValue":s[10]||(s[10]=o=>e.value.address.block_num=o),error:e.value.errors["address.block_num"]},{default:r(()=>[n(" Block Num ")]),_:1},8,["modelValue","error"])]),t("div",be,[l(u,{modelValue:e.value.address.building,"onUpdate:modelValue":s[11]||(s[11]=o=>e.value.address.building=o),error:e.value.errors["address.building"]},{default:r(()=>[n(" Building Name ")]),_:1},8,["modelValue","error"])]),t("div",ge,[l(u,{modelValue:e.value.address.street_name,"onUpdate:modelValue":s[12]||(s[12]=o=>e.value.address.street_name=o),required:"true",error:e.value.errors["address.street_name"]},{default:r(()=>[n(" Street Name ")]),_:1},8,["modelValue","error"])]),t("div",he,[xe,l(w,{modelValue:e.value.address.country_id,"onUpdate:modelValue":s[13]||(s[13]=o=>e.value.address.country_id=o),options:c.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),e.value.errors["address.country_id"]?(i(),v("div",Ve,g(e.value.errors["address.country_id"]),1)):f("",!0)]),t("div",ke,[l(u,{modelValue:e.value.address.latitude,"onUpdate:modelValue":s[14]||(s[14]=o=>e.value.address.latitude=o)},{default:r(()=>[n(" Latitude ")]),_:1},8,["modelValue"])]),t("div",we,[l(u,{modelValue:e.value.address.longitude,"onUpdate:modelValue":s[15]||(s[15]=o=>e.value.address.longitude=o)},{default:r(()=>[n(" Longitude ")]),_:1},8,["modelValue"])])]),t("div",Ue,[t("div",Se,[l(S,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:s[16]||(s[16]=o=>a.$emit("modalClose")),form:"submit"},{default:r(()=>[l(p(M),{class:"w-4 h-4"}),$e]),_:1}),l(S,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[l(p(F),{class:"w-4 h-4"}),Ne]),_:1})])])],40,Z)]),_:1},8,["open"])]))}};export{Oe as default};