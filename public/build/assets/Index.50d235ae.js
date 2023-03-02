import{_ as A,r as D}from"./Authenticated.fbf4be25.js";import{_ as h}from"./Button.330b9451.js";import H from"./Form.6f3dc90e.js";import T from"./VendForm.389ace6e.js";import{_ as U,r as R,T as b,a as z,b as w}from"./TableData.67ee7872.js";import{_ as K}from"./MultiSelect.389cb489.js";import{i as _,j as q,o as l,g as d,a as t,b as m,w as n,F as C,H as G,d as e,t as r,m as V,p,c as M,f,J as $,n as Q}from"./app.39868e0e.js";import{r as W}from"./PlusIcon.580dbe44.js";import{r as X}from"./BackspaceIcon.98eee3f4.js";import{r as Y}from"./PencilSquareIcon.015f0f45.js";import{r as Z}from"./TrashIcon.ce59f66e.js";import"./open-closed.067d63b7.js";import"./use-resolve-button-type.c7004e9d.js";import"./RectangleStackIcon.d33c432f.js";import"./FormInput.0d282dd3.js";import"./FormTextarea.8da40a35.js";import"./Modal.87d1d465.js";import"./PlusCircleIcon.b66709d0.js";import"./ArrowUturnLeftIcon.eb1cecbc.js";import"./CheckCircleIcon.f15779f3.js";import"./_plugin-vue_export-helper.cdc0426e.js";const ee=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Product Mappings ",-1),te={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},se={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},ne={class:"flex justify-end"},oe=e("span",null," Create ",-1),le={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},ae=f(" Name "),de={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ie={class:"mt-3"},re={class:"flex space-x-1"},ce=e("span",null," Search ",-1),ue=e("span",null," Reset ",-1),me={class:"flex flex-col space-y-2"},pe={class:"text-sm text-gray-700 leading-5 flex space-x-1"},fe=e("span",null,"Showing",-1),ge={class:"font-medium"},he=e("span",null,"to",-1),_e={class:"font-medium"},xe=e("span",null,"of",-1),ve={class:"font-medium"},ye=e("span",null,"results",-1),be={class:"mt-6 flex flex-col"},we={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ke={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Ce={class:"min-w-full border-separate",style:{"border-spacing":"0"}},$e={class:"bg-gray-100"},Pe={class:"divide-x divide-gray-200"},Ve=f(" # "),Me=f(" Name "),Le=f(" Channel - Product "),Be=f(" Binded Vending Machines "),Je={class:"bg-white"},Se={class:"divide-y divide-gray-200"},Fe={class:"flex py-1 px-3 space-x-2"},Ie={class:"text-blue-700 text-md pr-2"},Ne={key:0},je={class:"divide-y divide-gray-200"},Ee={class:"flex py-1 px-3 space-x-2"},Oe={key:0},Ae={class:"flex justify-center space-x-1"},De=e("span",null," Edit ",-1),He=e("span",null," VM Binding ",-1),Te={class:"flex space-x-1 items-center"},Ue=e("span",null," Delete ",-1),Re={key:0},ze={key:0},Ke=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),qe=[Ke],gt={__name:"Index",props:{products:Object,productMappings:Object,unbindedVends:Object},setup(o){const u=_({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),x=_(!1),k=_(!1),v=_(),y=_(""),P=_([]);q(()=>{P.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],u.value.numberPerPage=P.value[0]});function S(){y.value="create",v.value=null,x.value=!0}function F(c){!confirm("Are you sure to delete "+c.name+"?")||$.Inertia.delete("/product-mappings/"+c.id)}function I(c){y.value="update",v.value=c,x.value=!0}function N(c){y.value="update",v.value=c,$.Inertia.visit(route("product-mappings",{id:c.id}),{only:["unbindedVends"],preserveState:!0}),k.value=!0}function L(){$.Inertia.get("/product-mappings",{...u.value,numberPerPage:u.value.numberPerPage.id},{preserveState:!0,replace:!0})}function j(){$.Inertia.get("/product-mappings")}function E(){x.value=!1}function O(){k.value=!1}return(c,a)=>(l(),d(C,null,[t(m(G),{title:"Payment Methods"}),t(A,null,{header:n(()=>[ee]),default:n(()=>{var B,J;return[e("div",te,[e("div",se,[e("div",ne,[t(h,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[0]||(a[0]=s=>S())},{default:n(()=>[t(m(W),{class:"h-4 w-4","aria-hidden":"true"}),oe]),_:1})]),e("div",le,[t(U,{placeholderStr:"Name",modelValue:u.value.name,"onUpdate:modelValue":a[1]||(a[1]=s=>u.value.name=s)},{default:n(()=>[ae]),_:1},8,["modelValue"])]),e("div",de,[e("div",ie,[e("div",re,[t(h,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[2]||(a[2]=s=>L())},{default:n(()=>[t(m(R),{class:"h-4 w-4","aria-hidden":"true"}),ce]),_:1}),t(h,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[3]||(a[3]=s=>j())},{default:n(()=>[t(m(X),{class:"h-4 w-4","aria-hidden":"true"}),ue]),_:1})])]),e("div",me,[e("p",pe,[fe,e("span",ge,r((B=o.productMappings.meta.from)!=null?B:0),1),he,e("span",_e,r((J=o.productMappings.meta.to)!=null?J:0),1),xe,e("span",ve,r(o.productMappings.meta.total),1),ye]),t(K,{modelValue:u.value.numberPerPage,"onUpdate:modelValue":a[4]||(a[4]=s=>u.value.numberPerPage=s),options:P.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:L},null,8,["modelValue","options"])])])]),e("div",be,[e("div",we,[e("div",ke,[e("table",Ce,[e("thead",$e,[e("tr",Pe,[t(b,null,{default:n(()=>[Ve]),_:1}),t(b,null,{default:n(()=>[Me]),_:1}),t(b,null,{default:n(()=>[Le]),_:1}),t(b,null,{default:n(()=>[Be]),_:1}),t(b)])]),e("tbody",Je,[(l(!0),d(C,null,V(o.productMappings.data,(s,g)=>(l(),d("tr",{key:s.id,class:"divide-x divide-gray-200"},[t(w,{currentIndex:g,totalLength:o.productMappings.length,inputClass:"text-center"},{default:n(()=>[f(r(o.productMappings.meta.from+g),1)]),_:2},1032,["currentIndex","totalLength"]),t(w,{currentIndex:g,totalLength:o.productMappings.length,inputClass:"text-left"},{default:n(()=>[f(r(s.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(w,{currentIndex:g,totalLength:o.productMappings.length,inputClass:"text-left"},{default:n(()=>[e("ul",Se,[(l(!0),d(C,null,V(s.productMappingItemsJson,i=>(l(),d("li",Fe,[e("span",Ie,r(i.channel_code),1),i.product.code?(l(),d("span",Ne,r(i.product.code),1)):p("",!0),e("span",null," - "+r(i.product.name),1)]))),256))])]),_:2},1032,["currentIndex","totalLength"]),t(w,{currentIndex:g,totalLength:o.productMappings.length,inputClass:"text-left"},{default:n(()=>[e("ul",je,[(l(!0),d(C,null,V(s.vendsJson,i=>(l(),d("li",Ee,[i.full_name?(l(),d("span",Oe,r(i.full_name),1)):p("",!0)]))),256))])]),_:2},1032,["currentIndex","totalLength"]),t(w,{currentIndex:g,totalLength:o.productMappings.length,inputClass:"text-center"},{default:n(()=>[e("div",Ae,[t(h,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:i=>I(s)},{default:n(()=>[t(m(Y),{class:"w-4 h-4"}),De]),_:2},1032,["onClick"]),t(h,{type:"button",class:"bg-blue-300 hover:bg-blue-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:i=>N(s)},{default:n(()=>[t(m(D),{class:"w-4 h-4"}),He]),_:2},1032,["onClick"]),t(h,{type:"button",class:Q(["bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex-col space-y-1",[s.vendsJson&&s.vendsJson.length>0?"opacity-50 cursor-not-allowed":""]]),onClick:i=>F(s),disabled:s.vendsJson&&s.vendsJson.length>0},{default:n(()=>[e("span",Te,[t(m(Z),{class:"w-4 h-4"}),Ue]),s.vendsJson&&s.vendsJson.length>0?(l(),d("span",Re," (Binded) ")):p("",!0)]),_:2},1032,["class","onClick","disabled"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),o.productMappings.data.length?p("",!0):(l(),d("tr",ze,qe))])]),o.productMappings.data.length?(l(),M(z,{key:0,links:o.productMappings.links,meta:o.productMappings.meta},null,8,["links","meta"])):p("",!0)])])])]),x.value?(l(),M(H,{key:0,products:o.products,productMapping:v.value,type:y.value,showModal:x.value,onModalClose:E},null,8,["products","productMapping","type","showModal"])):p("",!0),k.value?(l(),M(T,{key:1,productMapping:v.value,type:y.value,showModal:k.value,unbindedVends:o.unbindedVends,onModalClose:O},null,8,["productMapping","type","showModal","unbindedVends"])):p("",!0)]}),_:1})],64))}};export{gt as default};
