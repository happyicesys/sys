import{_ as U,r as K}from"./Authenticated.d1b73c72.js";import{_ as h}from"./Button.47a00e17.js";import R from"./Form.caf7bd86.js";import z from"./VendForm.45c016ea.js";import{_ as b,a as T,b as k}from"./TableData.cc54b820.js";import{_ as Z,r as q}from"./SearchInput.4482b469.js";import{_ as G}from"./MultiSelect.03a43400.js";import{g as x,K as J,h as H,f as a,a as t,u as i,w as n,F as C,o as l,Z as Q,b as e,c as $,l as u,d as f,t as c,k as M,O as P,n as W}from"./app.2df6fe58.js";import{r as X}from"./PlusIcon.806caa52.js";import{r as Y}from"./BackspaceIcon.54ab7149.js";import{r as ee}from"./PencilSquareIcon.0fe1e8db.js";import{r as te}from"./TrashIcon.88e61502.js";import"./open-closed.5f597199.js";import"./use-resolve-button-type.5431d81c.js";import"./RectangleStackIcon.2fa47d4d.js";import"./FormInput.48525f12.js";import"./FormTextarea.9ec42f30.js";import"./Modal.c37b4e9f.js";import"./PlusCircleIcon.e592a73c.js";import"./ArrowUturnLeftIcon.05ce2cd6.js";import"./CheckCircleIcon.0d90e487.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.c208199c.js";const se=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Product Mappings ",-1),ne={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},oe={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},le={class:"flex justify-end"},ae=e("span",null," Create ",-1),de={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},re={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ie={class:"mt-3"},ue={class:"flex space-x-1"},ce=e("span",null," Search ",-1),me=e("span",null," Reset ",-1),pe={class:"flex flex-col space-y-2"},fe={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ge=e("span",null,"Showing",-1),he={class:"font-medium"},xe=e("span",null,"to",-1),_e={class:"font-medium"},ve=e("span",null,"of",-1),ye={class:"font-medium"},be=e("span",null,"results",-1),ke={class:"mt-6 flex flex-col"},we={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},Ce={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},$e={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Pe={class:"bg-gray-100"},Ve={class:"divide-x divide-gray-200"},Me={class:"bg-white"},Le={class:"divide-y divide-gray-200"},Be={class:"flex py-1 px-3 space-x-2"},Se={class:"text-blue-700 text-md pr-2"},Fe={key:0},Je={class:"divide-y divide-gray-200"},Ne={class:"flex py-1 px-3 space-x-2"},je={key:0},Oe={key:0,class:"flex justify-center space-x-1"},Ee=e("span",null," Edit ",-1),Ie=e("span",null," VM Binding ",-1),Ae={class:"flex space-x-1 items-center"},De=e("span",null," Delete ",-1),Ue={key:0},Ke={key:0},Re=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),ze=[Re],pt={__name:"Index",props:{products:Object,productMappings:Object,unbindedVends:Object},setup(o){const p=x({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),_=x(!1),w=x(!1),v=x(),y=x(""),V=x([]);J().props.auth.roles;const L=J().props.auth.permissions;H(()=>{V.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],p.value.numberPerPage=V.value[0]});function N(){y.value="create",v.value=null,_.value=!0}function j(m){!confirm("Are you sure to delete "+m.name+"?")||P.delete("/product-mappings/"+m.id)}function O(m){y.value="update",v.value=m,_.value=!0}function E(m){y.value="update",v.value=m,P.visit(route("product-mappings",{id:m.id}),{only:["unbindedVends"],preserveState:!0}),w.value=!0}function B(){P.get("/product-mappings",{...p.value,numberPerPage:p.value.numberPerPage.id},{preserveState:!0,replace:!0})}function I(){P.get("/product-mappings")}function A(){_.value=!1}function D(){w.value=!1}return(m,d)=>(l(),a(C,null,[t(i(Q),{title:"Payment Methods"}),t(U,null,{header:n(()=>[se]),default:n(()=>{var S,F;return[e("div",ne,[e("div",oe,[e("div",le,[i(L).includes("create product-mappings")?(l(),$(h,{key:0,class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:d[0]||(d[0]=s=>N())},{default:n(()=>[t(i(X),{class:"h-4 w-4","aria-hidden":"true"}),ae]),_:1})):u("",!0)]),e("div",de,[t(Z,{placeholderStr:"Name",modelValue:p.value.name,"onUpdate:modelValue":d[1]||(d[1]=s=>p.value.name=s)},{default:n(()=>[f(" Name ")]),_:1},8,["modelValue"])]),e("div",re,[e("div",ie,[e("div",ue,[t(h,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:d[2]||(d[2]=s=>B())},{default:n(()=>[t(i(q),{class:"h-4 w-4","aria-hidden":"true"}),ce]),_:1}),t(h,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:d[3]||(d[3]=s=>I())},{default:n(()=>[t(i(Y),{class:"h-4 w-4","aria-hidden":"true"}),me]),_:1})])]),e("div",pe,[e("p",fe,[ge,e("span",he,c((S=o.productMappings.meta.from)!=null?S:0),1),xe,e("span",_e,c((F=o.productMappings.meta.to)!=null?F:0),1),ve,e("span",ye,c(o.productMappings.meta.total),1),be]),t(G,{modelValue:p.value.numberPerPage,"onUpdate:modelValue":d[4]||(d[4]=s=>p.value.numberPerPage=s),options:V.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:B},null,8,["modelValue","options"])])])]),e("div",ke,[e("div",we,[e("div",Ce,[e("table",$e,[e("thead",Pe,[e("tr",Ve,[t(b,null,{default:n(()=>[f(" # ")]),_:1}),t(b,null,{default:n(()=>[f(" Name ")]),_:1}),t(b,null,{default:n(()=>[f(" Channel - Product ")]),_:1}),t(b,null,{default:n(()=>[f(" Binded Vending Machines ")]),_:1}),t(b)])]),e("tbody",Me,[(l(!0),a(C,null,M(o.productMappings.data,(s,g)=>(l(),a("tr",{key:s.id,class:"divide-x divide-gray-200"},[t(k,{currentIndex:g,totalLength:o.productMappings.length,inputClass:"text-center"},{default:n(()=>[f(c(o.productMappings.meta.from+g),1)]),_:2},1032,["currentIndex","totalLength"]),t(k,{currentIndex:g,totalLength:o.productMappings.length,inputClass:"text-left"},{default:n(()=>[f(c(s.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(k,{currentIndex:g,totalLength:o.productMappings.length,inputClass:"text-left"},{default:n(()=>[e("ul",Le,[(l(!0),a(C,null,M(s.productMappingItemsJson,r=>(l(),a("li",Be,[e("span",Se,c(r.channel_code),1),r.product.code?(l(),a("span",Fe,c(r.product.code),1)):u("",!0),e("span",null," - "+c(r.product.name),1)]))),256))])]),_:2},1032,["currentIndex","totalLength"]),t(k,{currentIndex:g,totalLength:o.productMappings.length,inputClass:"text-left"},{default:n(()=>[e("ul",Je,[(l(!0),a(C,null,M(s.vendsJson,r=>(l(),a("li",Ne,[r.full_name?(l(),a("span",je,c(r.full_name),1)):u("",!0)]))),256))])]),_:2},1032,["currentIndex","totalLength"]),t(k,{currentIndex:g,totalLength:o.productMappings.length,inputClass:"text-center"},{default:n(()=>[i(L).includes("update product-mappings")?(l(),a("div",Oe,[t(h,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:r=>O(s)},{default:n(()=>[t(i(ee),{class:"w-4 h-4"}),Ee]),_:2},1032,["onClick"]),t(h,{type:"button",class:"bg-blue-300 hover:bg-blue-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:r=>E(s)},{default:n(()=>[t(i(K),{class:"w-4 h-4"}),Ie]),_:2},1032,["onClick"]),t(h,{type:"button",class:W(["bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex-col space-y-1",[s.vendsJson&&s.vendsJson.length>0?"opacity-50 cursor-not-allowed":""]]),onClick:r=>j(s),disabled:s.vendsJson&&s.vendsJson.length>0},{default:n(()=>[e("span",Ae,[t(i(te),{class:"w-4 h-4"}),De]),s.vendsJson&&s.vendsJson.length>0?(l(),a("span",Ue," (Binded) ")):u("",!0)]),_:2},1032,["class","onClick","disabled"])])):u("",!0)]),_:2},1032,["currentIndex","totalLength"])]))),128)),o.productMappings.data.length?u("",!0):(l(),a("tr",Ke,ze))])]),o.productMappings.data.length?(l(),$(T,{key:0,links:o.productMappings.links,meta:o.productMappings.meta},null,8,["links","meta"])):u("",!0)])])])]),_.value?(l(),$(R,{key:0,products:o.products,productMapping:v.value,type:y.value,showModal:_.value,onModalClose:A},null,8,["products","productMapping","type","showModal"])):u("",!0),w.value?(l(),$(z,{key:1,productMapping:v.value,type:y.value,showModal:w.value,unbindedVends:o.unbindedVends,onModalClose:D},null,8,["productMapping","type","showModal","unbindedVends"])):u("",!0)]}),_:1})],64))}};export{pt as default};
