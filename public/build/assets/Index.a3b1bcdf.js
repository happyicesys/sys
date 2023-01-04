import{_ as O,r as A}from"./Authenticated.ed173437.js";import{_}from"./Button.24c8bfa0.js";import D from"./Form.57d1d2c3.js";import H from"./VendForm.f611a3a9.js";import{_ as U,r as R,T as k,a as z,b as q,c as b}from"./TableHeadSort.362a212c.js";import{_ as G}from"./MultiSelect.eca5a602.js";import{i as h,j as Q,o as a,g as r,a as t,b as m,w as n,F as C,H as W,d as e,t as c,m as B,p,c as M,f,J as $,n as X}from"./app.7e9af5a6.js";import{r as Y}from"./PlusIcon.83abb34c.js";import{r as Z}from"./BackspaceIcon.fa93b2fa.js";import{r as ee}from"./PencilSquareIcon.f1346029.js";import{r as te}from"./TrashIcon.dbc8c68a.js";import"./open-closed.44e72380.js";import"./use-resolve-button-type.550dcd40.js";import"./RectangleStackIcon.29ba1ab7.js";import"./FormInput.6342f540.js";import"./FormTextarea.89b683f0.js";import"./Modal.a480b1af.js";import"./PlusCircleIcon.1f73f88c.js";import"./ArrowUturnLeftIcon.0429b1f0.js";import"./CheckCircleIcon.017a5e8a.js";import"./_plugin-vue_export-helper.cdc0426e.js";const se=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Product Mappings ",-1),ne={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},oe={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},ae={class:"flex justify-end"},le=e("span",null," Create ",-1),de={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},re=f(" Name "),ie={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ue={class:"mt-3"},ce={class:"flex space-x-1"},me=e("span",null," Search ",-1),pe=e("span",null," Reset ",-1),fe={class:"flex flex-col space-y-2"},ge={class:"text-sm text-gray-700 leading-5 flex space-x-1"},_e=e("span",null,"Showing",-1),he={class:"font-medium"},xe=e("span",null,"to",-1),ve={class:"font-medium"},ye=e("span",null,"of",-1),be={class:"font-medium"},we=e("span",null,"results",-1),ke={class:"mt-6 flex flex-col"},Ce={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},$e={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Pe={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Ve={class:"bg-gray-100"},Be={class:"divide-x divide-gray-200"},Me=f(" # "),Le=f(" Name "),Se=f(" Channel - Product "),Je=f(" Binded Vending Machines "),Ne={class:"bg-white"},Fe={class:"divide-y divide-gray-200"},Ie={class:"flex py-1 px-3 space-x-2"},je={class:"text-blue-700 text-md pr-2"},Ke={key:0},Te={class:"divide-y divide-gray-200"},Ee={class:"flex py-1 px-3 space-x-2"},Oe={key:0},Ae={class:"flex justify-center space-x-1"},De=e("span",null," Edit ",-1),He=e("span",null," VM Binding ",-1),Ue={class:"flex space-x-1 items-center"},Re=e("span",null," Delete ",-1),ze={key:0},qe={key:0},Ge=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Qe=[Ge],ht={__name:"Index",props:{products:Object,productMappings:Object,unbindedVends:Object},setup(o){const d=h({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),x=h(!1),w=h(!1),v=h(),y=h(""),P=h([]);Q(()=>{P.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],d.value.numberPerPage=P.value[0]});function J(){y.value="create",v.value=null,x.value=!0}function N(i){!confirm("Are you sure to delete "+i.name+"?")||$.Inertia.delete("/product-mappings/"+i.id)}function F(i){y.value="update",v.value=i,x.value=!0}function I(i){y.value="update",v.value=i,$.Inertia.visit(route("product-mappings",{id:i.id}),{only:["unbindedVends"],preserveState:!0}),w.value=!0}function V(){$.Inertia.get("/product-mappings",{...d.value,numberPerPage:d.value.numberPerPage.id},{preserveState:!0,replace:!0})}function j(){$.Inertia.get("/product-mappings")}function K(i){d.value.sortKey=i,d.value.sortBy=!d.value.sortBy,V()}function T(){x.value=!1}function E(){w.value=!1}return(i,l)=>(a(),r(C,null,[t(m(W),{title:"Payment Methods"}),t(O,null,{header:n(()=>[se]),default:n(()=>{var L,S;return[e("div",ne,[e("div",oe,[e("div",ae,[t(_,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:l[0]||(l[0]=s=>J())},{default:n(()=>[t(m(Y),{class:"h-4 w-4","aria-hidden":"true"}),le]),_:1})]),e("div",de,[t(U,{placeholderStr:"Name",modelValue:d.value.name,"onUpdate:modelValue":l[1]||(l[1]=s=>d.value.name=s)},{default:n(()=>[re]),_:1},8,["modelValue"])]),e("div",ie,[e("div",ue,[e("div",ce,[t(_,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:l[2]||(l[2]=s=>V())},{default:n(()=>[t(m(R),{class:"h-4 w-4","aria-hidden":"true"}),me]),_:1}),t(_,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:l[3]||(l[3]=s=>j())},{default:n(()=>[t(m(Z),{class:"h-4 w-4","aria-hidden":"true"}),pe]),_:1})])]),e("div",fe,[e("p",ge,[_e,e("span",he,c((L=o.productMappings.meta.from)!=null?L:0),1),xe,e("span",ve,c((S=o.productMappings.meta.to)!=null?S:0),1),ye,e("span",be,c(o.productMappings.meta.total),1),we]),t(G,{modelValue:d.value.numberPerPage,"onUpdate:modelValue":l[4]||(l[4]=s=>d.value.numberPerPage=s),options:P.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:V},null,8,["modelValue","options"])])])]),e("div",ke,[e("div",Ce,[e("div",$e,[e("table",Pe,[e("thead",Ve,[e("tr",Be,[t(k,null,{default:n(()=>[Me]),_:1}),t(z,{modelName:"name",sortKey:d.value.sortKey,sortBy:d.value.sortBy,onSortTable:l[5]||(l[5]=s=>K("name"))},{default:n(()=>[Le]),_:1},8,["sortKey","sortBy"]),t(k,null,{default:n(()=>[Se]),_:1}),t(k,null,{default:n(()=>[Je]),_:1}),t(k)])]),e("tbody",Ne,[(a(!0),r(C,null,B(o.productMappings.data,(s,g)=>(a(),r("tr",{key:s.id,class:"divide-x divide-gray-200"},[t(b,{currentIndex:g,totalLength:o.productMappings.length,inputClass:"text-center"},{default:n(()=>[f(c(o.productMappings.meta.from+g),1)]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:g,totalLength:o.productMappings.length,inputClass:"text-left"},{default:n(()=>[f(c(s.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:g,totalLength:o.productMappings.length,inputClass:"text-left"},{default:n(()=>[e("ul",Fe,[(a(!0),r(C,null,B(s.productMappingItemsJson,u=>(a(),r("li",Ie,[e("span",je,c(u.channel_code),1),u.product.code?(a(),r("span",Ke,c(u.product.code),1)):p("",!0),e("span",null," - "+c(u.product.name),1)]))),256))])]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:g,totalLength:o.productMappings.length,inputClass:"text-left"},{default:n(()=>[e("ul",Te,[(a(!0),r(C,null,B(s.vendsJson,u=>(a(),r("li",Ee,[u.full_name?(a(),r("span",Oe,c(u.full_name),1)):p("",!0)]))),256))])]),_:2},1032,["currentIndex","totalLength"]),t(b,{currentIndex:g,totalLength:o.productMappings.length,inputClass:"text-center"},{default:n(()=>[e("div",Ae,[t(_,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:u=>F(s)},{default:n(()=>[t(m(ee),{class:"w-4 h-4"}),De]),_:2},1032,["onClick"]),t(_,{type:"button",class:"bg-blue-300 hover:bg-blue-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:u=>I(s)},{default:n(()=>[t(m(A),{class:"w-4 h-4"}),He]),_:2},1032,["onClick"]),t(_,{type:"button",class:X(["bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex-col space-y-1",[s.vendsJson&&s.vendsJson.length>0?"opacity-50 cursor-not-allowed":""]]),onClick:u=>N(s),disabled:s.vendsJson&&s.vendsJson.length>0},{default:n(()=>[e("span",Ue,[t(m(te),{class:"w-4 h-4"}),Re]),s.vendsJson&&s.vendsJson.length>0?(a(),r("span",ze," (Binded) ")):p("",!0)]),_:2},1032,["class","onClick","disabled"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),o.productMappings.data.length?p("",!0):(a(),r("tr",qe,Qe))])]),o.productMappings.data.length?(a(),M(q,{key:0,links:o.productMappings.links,meta:o.productMappings.meta},null,8,["links","meta"])):p("",!0)])])])]),x.value?(a(),M(D,{key:0,products:o.products,productMapping:v.value,type:y.value,showModal:x.value,onModalClose:T},null,8,["products","productMapping","type","showModal"])):p("",!0),w.value?(a(),M(H,{key:1,productMapping:v.value,type:y.value,showModal:w.value,unbindedVends:o.unbindedVends,onModalClose:E},null,8,["productMapping","type","showModal","unbindedVends"])):p("",!0)]}),_:1})],64))}};export{ht as default};
