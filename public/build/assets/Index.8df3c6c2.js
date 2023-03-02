import{_ as T}from"./Authenticated.a81153b4.js";import{_ as p}from"./Button.d03094fa.js";import O from"./Form.a9ec1304.js";import{_ as U,r as A,T as _,a as E,b as x}from"./TableData.1303af7a.js";import{_ as G}from"./MultiSelect.abaf8b0b.js";import{_ as H}from"./TableHeadSort.0b18be27.js";import{i as u,j as R,o as m,g as h,a as t,b as g,w as o,F as L,H as J,d as e,t as d,m as q,p as C,c as S,f as r,J as k}from"./app.a148a0ce.js";import{r as z}from"./PlusIcon.f9159983.js";import{r as Q}from"./BackspaceIcon.5a40654b.js";import{r as W}from"./PencilSquareIcon.e9149ddd.js";import{r as X}from"./TrashIcon.1d1f1922.js";import"./open-closed.ce385b24.js";import"./use-resolve-button-type.d397d356.js";import"./RectangleStackIcon.4f67b99f.js";import"./FormInput.171dd35c.js";import"./FormTextarea.4f67bcc7.js";import"./Modal.08e1db9d.js";import"./ArrowUturnLeftIcon.1f62befc.js";import"./CheckCircleIcon.e4e45e06.js";import"./_plugin-vue_export-helper.cdc0426e.js";const Y=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (Customer Categories) ",-1),Z={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},ee={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},te={class:"flex justify-end"},se=e("span",null," Create ",-1),oe={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},ae=r(" Name "),ne=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category Group ",-1),le={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},re={class:"mt-3"},ie={class:"flex space-x-1"},de=e("span",null," Search ",-1),ce=e("span",null," Reset ",-1),ue={class:"flex flex-col space-y-2"},me={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ge=e("span",null,"Showing",-1),fe={class:"font-medium"},pe=e("span",null,"to",-1),xe={class:"font-medium"},_e=e("span",null,"of",-1),he={class:"font-medium"},ve=e("span",null,"results",-1),ye={class:"mt-6 flex flex-col"},be={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},we={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Ce={class:"min-w-full border-separate",style:{"border-spacing":"0"}},ke={class:"bg-gray-100"},$e={class:"divide-x divide-gray-200"},Pe=r(" # "),Ve=r(" Name "),Be=r(" Desc "),Ge=r(" Category Group "),Le={class:"bg-white"},Se={class:"flex justify-center space-x-1"},Ie=e("span",null," Edit ",-1),Ne=e("span",null," Delete ",-1),je={key:0},Me=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),De=[Me],ot={__name:"Index",props:{categories:Object,categoryGroups:Object},setup(a){const $=a,l=u({name:"",categoryGroups:[],sortKey:"",sortBy:!0,numberPerPage:100}),f=u(!1),v=u(),y=u(""),b=u([]),P=u([]);R(()=>{P.value=$.categoryGroups.data,b.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],l.value.numberPerPage=b.value[0]});function I(){y.value="create",v.value=null,f.value=!0}function N(i){!confirm("Are you sure to delete "+i.name+"?")||k.Inertia.delete("/categories/"+i.id)}function j(i){y.value="update",v.value=i,f.value=!0}function w(){k.Inertia.get("/categories",{...l.value,numberPerPage:l.value.numberPerPage.id},{preserveState:!0,replace:!0})}function M(){k.Inertia.get("/categories")}function D(i){l.value.sortKey=i,l.value.sortBy=!l.value.sortBy,w()}function F(){f.value=!1}return(i,n)=>(m(),h(L,null,[t(g(J),{title:"Categories"}),t(T,null,{header:o(()=>[Y]),default:o(()=>{var V,B;return[e("div",Z,[e("div",ee,[e("div",te,[t(p,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[0]||(n[0]=s=>I())},{default:o(()=>[t(g(z),{class:"h-4 w-4","aria-hidden":"true"}),se]),_:1})]),e("div",oe,[t(U,{placeholderStr:"Name",modelValue:l.value.name,"onUpdate:modelValue":n[1]||(n[1]=s=>l.value.name=s)},{default:o(()=>[ae]),_:1},8,["modelValue"]),e("div",null,[ne,t(G,{modelValue:l.value.categoryGroups,"onUpdate:modelValue":n[2]||(n[2]=s=>l.value.categoryGroups=s),options:P.value,valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),e("div",le,[e("div",re,[e("div",ie,[t(p,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[3]||(n[3]=s=>w())},{default:o(()=>[t(g(A),{class:"h-4 w-4","aria-hidden":"true"}),de]),_:1}),t(p,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[4]||(n[4]=s=>M())},{default:o(()=>[t(g(Q),{class:"h-4 w-4","aria-hidden":"true"}),ce]),_:1})])]),e("div",ue,[e("p",me,[ge,e("span",fe,d((V=a.categories.meta.from)!=null?V:0),1),pe,e("span",xe,d((B=a.categories.meta.to)!=null?B:0),1),_e,e("span",he,d(a.categories.meta.total),1),ve]),t(G,{modelValue:l.value.numberPerPage,"onUpdate:modelValue":n[5]||(n[5]=s=>l.value.numberPerPage=s),options:b.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:w},null,8,["modelValue","options"])])])]),e("div",ye,[e("div",be,[e("div",we,[e("table",Ce,[e("thead",ke,[e("tr",$e,[t(_,null,{default:o(()=>[Pe]),_:1}),t(H,{modelName:"name",sortKey:l.value.sortKey,sortBy:l.value.sortBy,onSortTable:n[6]||(n[6]=s=>D("name"))},{default:o(()=>[Ve]),_:1},8,["sortKey","sortBy"]),t(_,null,{default:o(()=>[Be]),_:1}),t(_,null,{default:o(()=>[Ge]),_:1}),t(_)])]),e("tbody",Le,[(m(!0),h(L,null,q(a.categories.data,(s,c)=>(m(),h("tr",{key:s.id,class:"divide-x divide-gray-200"},[t(x,{currentIndex:c,totalLength:a.categories.length,inputClass:"text-center"},{default:o(()=>[r(d(a.categories.meta.from+c),1)]),_:2},1032,["currentIndex","totalLength"]),t(x,{currentIndex:c,totalLength:a.categories.length,inputClass:"text-left"},{default:o(()=>[r(d(s.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(x,{currentIndex:c,totalLength:a.categories.length,inputClass:"text-left whitespace-pre-wrap"},{default:o(()=>[r(d(s.desc),1)]),_:2},1032,["currentIndex","totalLength"]),t(x,{currentIndex:c,totalLength:a.categories.length,inputClass:"text-left"},{default:o(()=>[r(d(s.category_group_id?s.category_group_id.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),t(x,{currentIndex:c,totalLength:a.categories.length,inputClass:"text-center"},{default:o(()=>[e("div",Se,[t(p,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:K=>j(s)},{default:o(()=>[t(g(W),{class:"w-4 h-4"}),Ie]),_:2},1032,["onClick"]),t(p,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:K=>N(s)},{default:o(()=>[t(g(X),{class:"w-4 h-4"}),Ne]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),a.categories.data.length?C("",!0):(m(),h("tr",je,De))])]),a.categories.data.length?(m(),S(E,{key:0,links:a.categories.links,meta:a.categories.meta},null,8,["links","meta"])):C("",!0)])])])]),f.value?(m(),S(O,{key:0,category:v.value,categoryGroups:$.categoryGroups,type:y.value,showModal:f.value,onModalClose:F},null,8,["category","categoryGroups","type","showModal"])):C("",!0)]}),_:1})],64))}};export{ot as default};
