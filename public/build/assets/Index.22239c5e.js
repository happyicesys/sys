import{_ as K,r as R}from"./Authenticated.9fe60fc0.js";import{_ as h}from"./Button.33536ddb.js";import z from"./Form.031baa66.js";import T from"./VendForm.004169a1.js";import{_ as Z}from"./Paginator.af059acc.js";import{_ as J,r as q}from"./SearchInput.480cabcb.js";import{_ as G}from"./MultiSelect.35807366.js";import{_ as b,a as k}from"./TableData.d3c4c59d.js";import{g as _,K as N,h as H,f as d,a as t,u,w as n,F as C,o as l,Z as Q,b as e,c as V,l as c,d as f,t as m,k as M,O as $,n as W}from"./app.024e39e5.js";import{r as X}from"./PlusIcon.6cee21cd.js";import{r as Y}from"./BackspaceIcon.f40c1c84.js";import{r as ee}from"./PencilSquareIcon.991cf1bc.js";import{r as te}from"./TrashIcon.378da092.js";import"./open-closed.4c597dc4.js";import"./use-resolve-button-type.0e6f995d.js";import"./RectangleStackIcon.7a8167e6.js";import"./FormInput.a4bc002f.js";import"./FormTextarea.63f265c3.js";import"./Modal.e83c5b6a.js";import"./platform.ff812502.js";import"./PlusCircleIcon.cc3d7c7d.js";import"./ArrowUturnLeftIcon.00eea8ad.js";import"./CheckCircleIcon.71ce7f83.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.18709a47.js";const se=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Product Mappings ",-1),ne={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},oe={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},le={class:"flex justify-end"},ae=e("span",null," Create ",-1),de={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},re={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ie={class:"mt-3"},ue={class:"flex space-x-1"},ce=e("span",null," Search ",-1),me=e("span",null," Reset ",-1),pe={class:"flex flex-col space-y-2"},fe={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ge=e("span",null,"Showing",-1),he={class:"font-medium"},_e=e("span",null,"to",-1),xe={class:"font-medium"},ve=e("span",null,"of",-1),ye={class:"font-medium"},be=e("span",null,"results",-1),ke={class:"mt-6 flex flex-col"},we={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},Ce={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Ve={class:"min-w-full border-separate",style:{"border-spacing":"0"}},$e={class:"bg-gray-100"},Pe={class:"divide-x divide-gray-200"},Me={class:"bg-white"},Le={class:"divide-y divide-gray-200"},Se={class:"flex py-1 px-3 space-x-2"},Be={class:"text-blue-700 text-md pr-2"},Fe={key:0},Je={class:"divide-y divide-gray-200"},Ne={class:"flex py-1 px-3 space-x-2"},je={key:0},Ie={key:0,class:"flex justify-center flex-col space-y-1"},Oe=e("span",null," Edit ",-1),De=e("span",null," VM Binding ",-1),Ee={class:"flex space-x-1 items-center"},Ue=e("span",null," Delete ",-1),Ae={key:0},Ke={key:0},Re=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),ze=[Re],gt={__name:"Index",props:{products:Object,productMappings:Object,unbindedVends:Object},setup(o){const i=_({name:"",vend_code:"",sortKey:"",sortBy:!0,numberPerPage:100}),x=_(!1),w=_(!1),v=_(),y=_(""),P=_([]);N().props.auth.roles;const L=N().props.auth.permissions;H(()=>{P.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],i.value.numberPerPage=P.value[0]});function j(){y.value="create",v.value=null,x.value=!0}function I(p){!confirm("Are you sure to delete "+p.name+"?")||$.delete("/product-mappings/"+p.id)}function O(p){y.value="update",v.value=p,x.value=!0}function D(p){y.value="update",v.value=p,$.visit(route("product-mappings",{id:p.id}),{only:["unbindedVends"],preserveState:!0}),w.value=!0}function S(){$.get("/product-mappings",{...i.value,numberPerPage:i.value.numberPerPage.id},{preserveState:!0,replace:!0})}function E(){$.get("/product-mappings")}function U(){x.value=!1}function A(){w.value=!1}return(p,a)=>(l(),d(C,null,[t(u(Q),{title:"Product Mappings"}),t(K,null,{header:n(()=>[se]),default:n(()=>{var B,F;return[e("div",ne,[e("div",oe,[e("div",le,[u(L).includes("create product-mappings")?(l(),V(h,{key:0,class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[0]||(a[0]=s=>j())},{default:n(()=>[t(u(X),{class:"h-4 w-4","aria-hidden":"true"}),ae]),_:1})):c("",!0)]),e("div",de,[t(J,{placeholderStr:"Name",modelValue:i.value.name,"onUpdate:modelValue":a[1]||(a[1]=s=>i.value.name=s)},{default:n(()=>[f(" Name ")]),_:1},8,["modelValue"]),t(J,{placeholderStr:"Vend ID",modelValue:i.value.vend_code,"onUpdate:modelValue":a[2]||(a[2]=s=>i.value.vend_code=s)},{default:n(()=>[f(" Vend ID# ")]),_:1},8,["modelValue"])]),e("div",re,[e("div",ie,[e("div",ue,[t(h,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[3]||(a[3]=s=>S())},{default:n(()=>[t(u(q),{class:"h-4 w-4","aria-hidden":"true"}),ce]),_:1}),t(h,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[4]||(a[4]=s=>E())},{default:n(()=>[t(u(Y),{class:"h-4 w-4","aria-hidden":"true"}),me]),_:1})])]),e("div",pe,[e("p",fe,[ge,e("span",he,m((B=o.productMappings.meta.from)!=null?B:0),1),_e,e("span",xe,m((F=o.productMappings.meta.to)!=null?F:0),1),ve,e("span",ye,m(o.productMappings.meta.total),1),be]),t(G,{modelValue:i.value.numberPerPage,"onUpdate:modelValue":a[5]||(a[5]=s=>i.value.numberPerPage=s),options:P.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:S},null,8,["modelValue","options"])])])]),e("div",ke,[e("div",we,[e("div",Ce,[e("table",Ve,[e("thead",$e,[e("tr",Pe,[t(b,null,{default:n(()=>[f(" # ")]),_:1}),t(b,null,{default:n(()=>[f(" Name ")]),_:1}),t(b,null,{default:n(()=>[f(" Channel - Product ")]),_:1}),t(b,null,{default:n(()=>[f(" Binded Vending Machines ")]),_:1}),t(b)])]),e("tbody",Me,[(l(!0),d(C,null,M(o.productMappings.data,(s,g)=>(l(),d("tr",{key:s.id,class:"divide-x divide-gray-200"},[t(k,{currentIndex:g,totalLength:o.productMappings.length,inputClass:"text-center"},{default:n(()=>[f(m(o.productMappings.meta.from+g),1)]),_:2},1032,["currentIndex","totalLength"]),t(k,{currentIndex:g,totalLength:o.productMappings.length,inputClass:"text-left"},{default:n(()=>[f(m(s.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(k,{currentIndex:g,totalLength:o.productMappings.length,inputClass:"text-left"},{default:n(()=>[e("ul",Le,[(l(!0),d(C,null,M(s.productMappingItemsJson,r=>(l(),d("li",Se,[e("span",Be,m(r.channel_code),1),r.product.code?(l(),d("span",Fe,m(r.product.code),1)):c("",!0),e("span",null," - "+m(r.product.name),1)]))),256))])]),_:2},1032,["currentIndex","totalLength"]),t(k,{currentIndex:g,totalLength:o.productMappings.length,inputClass:"text-left"},{default:n(()=>[e("ul",Je,[(l(!0),d(C,null,M(s.vendsJson,r=>(l(),d("li",Ne,[r.full_name?(l(),d("span",je,m(r.full_name),1)):c("",!0)]))),256))])]),_:2},1032,["currentIndex","totalLength"]),t(k,{currentIndex:g,totalLength:o.productMappings.length,inputClass:"text-center"},{default:n(()=>[u(L).includes("update product-mappings")?(l(),d("div",Ie,[t(h,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:r=>O(s)},{default:n(()=>[t(u(ee),{class:"w-4 h-4"}),Oe]),_:2},1032,["onClick"]),t(h,{type:"button",class:"bg-blue-300 hover:bg-blue-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:r=>D(s)},{default:n(()=>[t(u(R),{class:"w-4 h-4"}),De]),_:2},1032,["onClick"]),t(h,{type:"button",class:W(["bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex-col space-y-1",[s.vendsJson&&s.vendsJson.length>0?"opacity-50 cursor-not-allowed":""]]),onClick:r=>I(s),disabled:s.vendsJson&&s.vendsJson.length>0},{default:n(()=>[e("span",Ee,[t(u(te),{class:"w-4 h-4"}),Ue]),s.vendsJson&&s.vendsJson.length>0?(l(),d("span",Ae," (Binded) ")):c("",!0)]),_:2},1032,["class","onClick","disabled"])])):c("",!0)]),_:2},1032,["currentIndex","totalLength"])]))),128)),o.productMappings.data.length?c("",!0):(l(),d("tr",Ke,ze))])]),o.productMappings.data.length?(l(),V(Z,{key:0,links:o.productMappings.links,meta:o.productMappings.meta},null,8,["links","meta"])):c("",!0)])])])]),x.value?(l(),V(z,{key:0,products:o.products,productMapping:v.value,type:y.value,showModal:x.value,onModalClose:U},null,8,["products","productMapping","type","showModal"])):c("",!0),w.value?(l(),V(T,{key:1,productMapping:v.value,type:y.value,showModal:w.value,unbindedVends:o.unbindedVends,onModalClose:A},null,8,["productMapping","type","showModal","unbindedVends"])):c("",!0)]}),_:1})],64))}};export{gt as default};
